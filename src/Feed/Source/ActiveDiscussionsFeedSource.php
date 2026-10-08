<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed\Source;

use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\DiscussionThread;
use MediaWiki\Extension\PersonalDashboard\Feed\DiscussionThreadFeedItem;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedSourceResult;
use MediaWiki\Extension\PersonalDashboard\Feed\IDiscussionThreadLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\IFeedSource;
use MediaWiki\Page\PageStore;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Discussions with recent replies, from the talk pages the wiki names.
 *
 * $wgPersonalDashboardActiveDiscussionsPages holds the page names: a village
 * pump, a noticeboard, wherever the wiki talks. The threads themselves come from
 * the thread lookup, which caches each page for itself, so what this source owns
 * is which pages it reads, which threads it keeps, and the order it puts them in.
 *
 * It is registered whether or not DiscussionTools is installed. Without it the
 * lookup finds nothing and the feed answers empty, which beats making
 * `sources=activediscussions` a 400 on some wikis and not others.
 *
 * A thread's place in the order is its newest reply, which moves. A walk
 * through the feed is therefore a view of one moment: a thread that gains a
 * reply mid-walk sorts above the cursor, and the rest of that walk never
 * reaches it. A page the lookup could not read is lost to that walk too. The
 * feed samples what is active; it is not a list to be read to the end.
 */
class ActiveDiscussionsFeedSource implements IFeedSource {

	private string $name = '';

	public function __construct(
		private readonly Config $mainConfig,
		private readonly PageStore $pageStore,
		private readonly IDiscussionThreadLookup $threadLookup,
		private readonly LoggerInterface $logger,
	) {
	}

	/** @inheritDoc */
	public function setName( string $name ): void {
		$this->name = $name;
	}

	/** @inheritDoc */
	public function getItemSchema(): array {
		return [
			'type' => 'object',
			'required' => [ 'id', 'feedorigin', 'title', 'pageid', 'timestamp', 'section' ],
			'properties' => [
				'id' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-id',
					'example' => 'activediscussions-77-h-Survey-20260310120000',
				],
				'feedorigin' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-feedorigin',
					'example' => 'activediscussions',
				],
				'title' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-title',
					'example' => 'Wikipedia:Village pump',
				],
				'pageid' => [
					'type' => 'integer',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-pageid',
					'example' => 77,
				],
				'timestamp' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-timestamp',
					'example' => '2026-03-10T12:00:00Z',
				],
				'section' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-section',
					'example' => 'Survey',
				],
				// Empty where the headline carries no id of its own.
				'anchor' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-anchor',
					'example' => 'Survey',
				],
				// Null for a thread that is already a top-level discussion.
				'topic' => [
					'type' => 'string',
					'nullable' => true,
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-topic',
					'example' => 'Requested move of Jupiter',
				],
				'commentCount' => [
					'type' => 'integer',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-comment-count',
					'example' => 12,
				],
				'authorCount' => [
					'type' => 'integer',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-author-count',
					'example' => 4,
				],
				'latestReplyId' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-property-desc-thread-latest-reply-id',
					'example' => 'c-Carol-20260310120000-Alice-20260309090000',
				],
			],
		];
	}

	/** @inheritDoc */
	public function getItems( FeedRequest $request ): FeedSourceResult {
		$items = [];

		foreach ( $this->getConfiguredPages() as $page ) {
			if ( !$request->authority->probablyCan( 'read', $page ) ) {
				continue;
			}

			try {
				$result = $this->threadLookup->getThreadsForPage( $page );
			} catch ( Throwable $e ) {
				/*
				 * One page failing must not cost the viewer the other pages'
				 * threads or the other sources' items, so log it and carry on
				 * with a short answer.
				 */
				$this->logger->error(
					'PersonalDashboard: failed to read discussion threads from {page}',
					[ 'page' => Title::newFromPageIdentity( $page )->getPrefixedText(), 'exception' => $e ]
				);
				continue;
			}

			array_push( $items, ...$this->buildItems( $page, $result->threads ) );
		}

		usort(
			$items,
			static fn ( DiscussionThreadFeedItem $a, DiscussionThreadFeedItem $b ): int
				=> self::compareSortKeys( $a->getSortKey(), $b->getSortKey() )
		);

		/*
		 * Resume by position in that order, never by offset: FeedMerger hands
		 * back the cursor of the last item the merge walked past, which is not
		 * always the last item this source returned.
		 */
		$cursor = self::parseCursor( $request->cursor );
		if ( $cursor !== null ) {
			$items = array_values( array_filter(
				$items,
				static fn ( DiscussionThreadFeedItem $item ): bool
					=> self::compareSortKeys( $item->getSortKey(), $cursor ) > 0
			) );
		}

		/*
		 * A page we couldn't read doesn't count as more to come. The next request
		 * reads it again, but by then only its threads older than the cursor can
		 * count, so the promise mostly buys a "Show more" that adds nothing.
		 */
		return new FeedSourceResult(
			array_slice( $items, 0, $request->limit ),
			count( $items ) > $request->limit
		);
	}

	/**
	 * Resolve the configured page names, each page once.
	 *
	 * Two names can reach one page, and nothing downstream would collapse the
	 * items that follow: a thread has no dedup key, because threads are already
	 * unique, so the merge would carry both copies under one id.
	 *
	 * @return ProperPageIdentity[]
	 */
	private function getConfiguredPages(): array {
		$pages = [];
		foreach ( $this->mainConfig->get( 'PersonalDashboardActiveDiscussionsPages' ) as $pageName ) {
			$page = $this->pageStore->getPageByText( (string)$pageName );
			if ( $page === null ) {
				// A name no title parser accepts, so a wiki has misconfigured
				// this and no later request will do any better.
				$this->logger->warning(
					'PersonalDashboard: {page} is not a usable discussion page name',
					[ 'page' => (string)$pageName ]
				);
				continue;
			}

			$pages[ $page->getNamespace() . ':' . $page->getDBkey() ] = $page;
		}

		return array_values( $pages );
	}

	/**
	 * @param ProperPageIdentity $page
	 * @param DiscussionThread[] $threads
	 * @return DiscussionThreadFeedItem[]
	 */
	private function buildItems( ProperPageIdentity $page, array $threads ): array {
		$title = Title::newFromPageIdentity( $page )->getPrefixedText();

		$items = [];
		foreach ( $threads as $thread ) {
			/*
			 * Nobody has answered, so there is no discussion to join yet. The
			 * count covers a heading's sub-headings too, so an RfC whose lead
			 * section only its proposer wrote still counts its survey's authors.
			 */
			if ( $thread->authorCount <= 1 ) {
				continue;
			}

			$items[] = new DiscussionThreadFeedItem(
				source: $this->name,
				title: $title,
				pageId: $page->getId(),
				thread: $thread,
			);
		}

		return $items;
	}

	/**
	 * Read a cursor DiscussionThreadFeedItem::getCursor() wrote.
	 *
	 * A cursor arrives from the client, so an unreadable one starts again at the
	 * newest item rather than failing the request.
	 *
	 * @param string|null $cursor
	 * @return array{0:string,1:int,2:string}|null
	 */
	private static function parseCursor( ?string $cursor ): ?array {
		if ( $cursor === null ) {
			return null;
		}

		$parts = explode( '|', $cursor );
		if ( count( $parts ) !== 3 || !ctype_digit( $parts[1] ) ) {
			return null;
		}

		return [ $parts[0], (int)$parts[1], rawurldecode( $parts[2] ) ];
	}

	/**
	 * Order two sort keys, newest reply first.
	 *
	 * Timestamps are ISO-8601, so comparing them as strings orders them
	 * correctly.
	 *
	 * @param array{0:string,1:int,2:string} $a
	 * @param array{0:string,1:int,2:string} $b
	 * @return int
	 */
	private static function compareSortKeys( array $a, array $b ): int {
		return strcmp( $b[0], $a[0] )
			?: $a[1] <=> $b[1]
			?: strcmp( $a[2], $b[2] );
	}
}
