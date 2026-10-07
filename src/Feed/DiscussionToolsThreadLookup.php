<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Extension\DiscussionTools\ContentThreadItemSet;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentHeadingItem;
use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Page\PageStore;
use MediaWiki\Page\ParserOutputAccess;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use Throwable;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\IWANCacheBuilder;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * The one class in this extension that names a DiscussionTools class.
 *
 * ServiceWiring builds it only when DiscussionTools is loaded; a wiki without
 * it gets NullDiscussionThreadLookup instead.
 *
 * The Parsoid output this needs is usually in the parser cache already, left
 * there by ordinary page views wherever Parsoid is the read parser, and by
 * DiscussionTools' edit-time warming where that is turned on (its
 * DataUpdatesHooks). This class probes for it rather than asking for a parse:
 * there is no PoolCounter on the path, so a parse here would be an unprotected
 * synchronous parse inside a REST GET.
 *
 * A probe that hits also carries the call that follows it: getCachedParserOutput()
 * keeps what it found in ParserOutputAccess's own in-process cache, and the
 * getParserOutput() inside HookUtils::parseRevisionParsoidHtml() reads that
 * before it renders anything. We and DiscussionTools build the same parser
 * options and share the one service instance, so the second lookup cannot reach
 * the parser.
 *
 * What that leaves is an edit arriving between the two. DiscussionTools looks the
 * page up for itself rather than taking ours, so a revision it can see and we
 * could not gives it a page whose latest is not the one we probed, and the parse
 * we were avoiding happens after all.
 */
class DiscussionToolsThreadLookup implements IDiscussionThreadLookup {

	public function __construct(
		private readonly PageStore $pageStore,
		private readonly RevisionLookup $revisionLookup,
		private readonly ParserOutputAccess $parserOutputAccess,
		private readonly IWANCacheBuilder $cache,
		private readonly StatsFactory $statsFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/** @inheritDoc */
	public function getThreadsForPage( ProperPageIdentity $page ): DiscussionThreadResult {
		$pageRecord = $this->pageStore->getPageByReference( $page );
		if ( $pageRecord === null ) {
			// Nothing to discover later for a page that does not exist, so this
			// is a real answer, not a gap to retry.
			return DiscussionThreadResult::newEmpty();
		}

		return $this->cache->buildGetWithSetCallback()
			->key( 'personaldashboard', 'activediscussions-threads', $pageRecord->getId() )
			->keepForADay()
			->valueVersion( 1 )
			// Keyed on page id, not revision id, so an edit has to invalidate
			// this rather than move the key.
			->lastModifiedCallback(
				static fn () => (int)wfTimestamp( TimestampFormat::UNIX, $pageRecord->getTouched() )
			)
			->allowStale( 30 )
			// Not an empty result, which would read as "no discussions" to a
			// caller that has no way to tell a degraded answer from a real one.
			->busyValue( DiscussionThreadResult::newDegraded() )
			->shortProcessCache()
			->callback( function ( $oldValue, &$ttl ) use ( $pageRecord ) {
				$result = $this->fetchThreadsForPage( $pageRecord );
				if ( !$result->complete ) {
					$ttl = ExpirationAwareness::TTL_MINUTE;
					return $oldValue ?: $result;
				}

				return $result;
			} )
			->fetch();
	}

	/**
	 * @param ExistingPageRecord $pageRecord
	 * @return DiscussionThreadResult Incomplete where the parser cache had
	 *   nothing to probe or the parse failed, both of which are worth another
	 *   try shortly.
	 */
	private function fetchThreadsForPage( ExistingPageRecord $pageRecord ): DiscussionThreadResult {
		$title = Title::castFromPageIdentity( $pageRecord );
		if ( !$title || !HookUtils::isAvailableForTitle( $title ) ) {
			return DiscussionThreadResult::newEmpty();
		}

		// Passing $pageRecord lets the lookup skip a second page query.
		$revision = $this->revisionLookup->getRevisionById( $pageRecord->getLatest(), 0, $pageRecord );
		if ( !$revision ) {
			// No revision to read comments from: a real, final answer, not a
			// probe failure to retry.
			return DiscussionThreadResult::newEmpty();
		}

		/*
		 * These have to be the options HookUtils::parseRevisionParsoidHtml()
		 * builds for itself, or the probe reads a different parser-cache key
		 * than the call below. Nothing links the two, so a DiscussionTools
		 * change here fails silently in both directions: a permanent miss on a
		 * warm cache, or a parse on a cold one.
		 */
		$parserOptions = ParserOptions::newFromAnon();
		$parserOptions->setUseParsoid();

		if ( $this->parserOutputAccess->getCachedParserOutput( $pageRecord, $parserOptions, $revision ) === null ) {
			$this->recordProbe( 'miss' );
			return DiscussionThreadResult::newDegraded();
		}

		try {
			// `false`, not __METHOD__: a dashboard GET must never become a
			// parser-cache write path.
			$threadItemSet = HookUtils::parseRevisionParsoidHtml( $revision, false )->getValueOrThrow();
			$threads = $this->buildThreads( $threadItemSet );
		} catch ( Throwable $e ) {
			/*
			 * Caught the way DiscussionTools' own DataUpdatesHooks does for
			 * T315383: logged, not fatal. buildThreads() is inside the guard
			 * because it throws too, from getLinkableId() and the DOM reads
			 * behind getText(), and a throw out of here escapes the cache
			 * callback as a 500 on a path built to degrade.
			 */
			$this->recordProbe( 'error' );
			$this->logger->error(
				'PersonalDashboard: failed to parse discussion threads for {page}',
				[ 'page' => $title->getPrefixedText(), 'exception' => $e ]
			);
			return DiscussionThreadResult::newDegraded();
		}

		$this->recordProbe( 'hit' );

		return new DiscussionThreadResult( $threads, true );
	}

	/**
	 * Turn every heading into one thread, counted the way DiscussionTools
	 * counts it.
	 *
	 * getThreads() lists a sub-heading in its own right as well as under the
	 * heading above it, and DiscussionTools' own counts cover everything below
	 * the heading they are asked about. So an RfC and its "Survey" sub-section
	 * are both threads, and the RfC's totals include the Survey's, which is
	 * what the talk page shows under each of those headings.
	 *
	 * @param ContentThreadItemSet $threadItemSet
	 * @return DiscussionThread[]
	 */
	private function buildThreads( ContentThreadItemSet $threadItemSet ): array {
		$threads = [];
		foreach ( $threadItemSet->getThreads() as $heading ) {
			$latestReply = $heading->getLatestReply();
			// Comments above the first heading belong to no thread, and a heading
			// nobody has signed anything under is not a discussion.
			if ( $heading->isPlaceholderHeading() || $latestReply === null ) {
				continue;
			}

			$threads[] = new DiscussionThread(
				itemId: $heading->getId(),
				section: $heading->getText(),
				anchor: $heading->getLinkableId(),
				topic: $this->findTopic( $heading )?->getText(),
				commentCount: $heading->getCommentCount(),
				authorCount: count( $heading->getAuthorsBelow() ),
				latestReplyTimestamp: wfTimestamp(
					TimestampFormat::ISO_8601, $latestReply->getTimestamp()->getTimestamp()
				),
				latestReplyId: $latestReply->getId(),
			);
		}

		return $threads;
	}

	/**
	 * The outermost heading $heading sits under, or null where it is already
	 * one.
	 *
	 * Not the heading directly above it: on a page nested deeper than two
	 * levels that one is a sub-heading too, and naming it gives a card no more
	 * context than its own title already does.
	 */
	private function findTopic( ContentHeadingItem $heading ): ?ContentHeadingItem {
		$topic = null;
		for ( $ancestor = $heading->getParent(); $ancestor !== null; $ancestor = $ancestor->getParent() ) {
			if ( $ancestor instanceof ContentHeadingItem ) {
				$topic = $ancestor;
			}
		}

		return $topic;
	}

	private function recordProbe( string $result ): void {
		$this->statsFactory->withComponent( 'PersonalDashboard' )
			->getCounter( 'discussion_thread_lookup_probe_total' )
			->setLabel( 'result', $result )
			->increment();
	}
}
