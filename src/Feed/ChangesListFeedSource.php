<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\ChangeTags\ChangeTags;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQueryFactory;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * Base class for every feed source that reads the recentchanges table.
 *
 * The three Review Changes sources ask almost the same question, so the shared
 * query lives here and a subclass adds only what makes it different: the
 * watchlist join, the recently edited page set, or nothing at all. Each returns
 * RecentChangeFeedItem, so one card renders all three.
 *
 * Two of the filters are viewer-relative, and they are options rather than part
 * of the chain: Review Changes will gain a setting that turns personalization
 * off and falls back to a plain recent changes query. That setting registers
 * this same class a second time with the options off, so it needs no new class.
 */
abstract class ChangesListFeedSource implements IFeedSource {

	/**
	 * Tags that mark an edit somebody already dealt with.
	 *
	 * excludeChangeTags() drops these in SQL. The client used to drop them after
	 * the fact, which cost it items out of a limit it had already paid for.
	 */
	protected const array EXCLUDED_TAGS = [
		ChangeTags::TAG_REVERTED,
		ChangeTags::TAG_UNDO,
		ChangeTags::TAG_ROLLBACK,
	];

	protected string $name = '';

	/** Whether to hide the viewer's own edits. Viewer-relative. */
	private bool $excludeSelf;

	/** Whether to show only unpatrolled edits, for a viewer who can patrol. Viewer-relative. */
	private bool $unpatrolledOnly;

	/**
	 * @param ChangesListQueryFactory $changesListQueryFactory
	 * @param IConnectionProvider $connectionProvider
	 * @param RowCommentFormatter $rowCommentFormatter
	 * @param Config $mainConfig
	 * @param array $options Set through the ObjectFactory spec's `args` key:
	 *   - excludeSelf: (bool, default true) hide the viewer's own edits.
	 *   - unpatrolledOnly: (bool, default true) show only unpatrolled edits when
	 *     the viewer holds the `patrol` right.
	 *   Turn both off to get a feed that does not depend on who is asking.
	 */
	public function __construct(
		protected readonly ChangesListQueryFactory $changesListQueryFactory,
		protected readonly IConnectionProvider $connectionProvider,
		private readonly RowCommentFormatter $rowCommentFormatter,
		private readonly Config $mainConfig,
		array $options = [],
	) {
		$this->excludeSelf = $options['excludeSelf'] ?? true;
		$this->unpatrolledOnly = $options['unpatrolledOnly'] ?? true;
	}

	/** @inheritDoc */
	public function setName( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Narrow the shared query to this source.
	 *
	 * Return false to skip the query altogether, for a source that can tell it
	 * has nothing to offer this viewer before it asks the database.
	 *
	 * @param ChangesListQuery $query
	 * @param FeedRequest $request
	 * @return bool
	 */
	abstract protected function applySourceFilters(
		ChangesListQuery $query,
		FeedRequest $request
	): bool;

	/** @inheritDoc */
	public function getItems( FeedRequest $request ): FeedSourceResult {
		$cursor = $this->parseCursor( $request->cursor );

		// One row past the limit tells us whether another page exists. startAt()
		// is inclusive, so with a cursor the anchor row comes back too and needs
		// one more row of headroom.
		$fetchLimit = $request->limit + 1 + ( $cursor !== null ? 1 : 0 );

		$query = $this->newBaseQuery( $request, $fetchLimit );
		if ( $cursor !== null ) {
			$query->startAt( $cursor[0], $cursor[1] );
		}

		if ( !$this->applySourceFilters( $query, $request ) ) {
			return FeedSourceResult::newEmpty();
		}

		$rows = iterator_to_array(
			$query->fetchResult()->getResultWrapper(),
			false
		);

		// Drop the anchor. The row may have been purged since the last request,
		// in which case there is nothing to drop and nothing is lost.
		if ( $cursor !== null && $rows && (int)$rows[0]->rc_id === $cursor[1] ) {
			array_shift( $rows );
		}

		$mayHaveMore = count( $rows ) > $request->limit;
		$rows = array_slice( $rows, 0, $request->limit );

		return new FeedSourceResult( $this->buildItems( $rows, $request ), $mayHaveMore );
	}

	/**
	 * Build the query every source shares.
	 *
	 * @param FeedRequest $request
	 * @param int $fetchLimit
	 * @return ChangesListQuery
	 */
	protected function newBaseQuery( FeedRequest $request, int $fetchLimit ): ChangesListQuery {
		$query = $this->changesListQueryFactory->newQuery()
			->audience( $request->authority )
			->recentChangeFields()
			->addChangeTagSummaryField()
			->requireNamespaces( [ NS_MAIN ] )
			->requireSources( [ RecentChange::SRC_EDIT ] )
			->excludeChangeTags( self::EXCLUDED_TAGS )
			// audience() only reads these; without them it has nothing to hide.
			->excludeDeletedUser()
			->excludeDeletedLogAction()
			->orderBy( ChangesListQuery::SORT_TIMESTAMP_DESC )
			->limit( $fetchLimit )
			->caller( static::class . '::getItems' );

		// recentchanges is pruned to $wgRCMaxAge, so nothing older can be found.
		// Saying so keeps a deep page from scanning for rows that cannot exist.
		$maxAge = $this->mainConfig->get( MainConfigNames::RCMaxAge );
		$query->minTimestamp(
			wfTimestamp( TimestampFormat::MW, (int)wfTimestamp() - $maxAge )
		);

		// The client filters bots with rcshow=!bot. ChangesListQuery exposes that
		// only through applyAction(), which is @internal, so use the plain
		// expression the module this replaces already used.
		$query->where(
			$this->connectionProvider->getReplicaDatabase()->expr( 'rc_bot', '=', 0 )
		);

		if ( $this->excludeSelf ) {
			$query->excludeUser( $request->authority->getUser() );
		}

		// The right is what the client checks. Note $wgUseRCPatrol can be off,
		// which makes rc_patrolled meaningless even for a user who holds the
		// right; core's own useRCPatrol() covers both. Kept as-is so this stays
		// a port, not a behaviour change.
		if ( $this->unpatrolledOnly && $request->authority->isAllowed( 'patrol' ) ) {
			$query->requirePatrolled( RecentChange::PRC_UNPATROLLED );
		}

		return $query;
	}

	/**
	 * Turn recentchanges rows into feed items.
	 *
	 * @param stdClass[] $rows
	 * @param FeedRequest $request
	 * @return RecentChangeFeedItem[]
	 */
	private function buildItems( array $rows, FeedRequest $request ): array {
		// Format every summary in one pass. Done per row, each one resolves its
		// own wikilinks, so a page of items costs a page of lookups.
		$comments = $this->rowCommentFormatter->formatRows(
			$rows,
			'rc_comment',
			'rc_namespace',
			'rc_title',
			'rc_id',
		);

		$items = [];
		foreach ( $rows as $row ) {
			$canSeeComment = RevisionRecord::userCanBitfield(
				$row->rc_deleted,
				RevisionRecord::DELETED_COMMENT,
				$request->authority,
			);

			$items[] = new RecentChangeFeedItem(
				source: $this->name,
				rcId: (int)$row->rc_id,
				title: Title::makeTitle( $row->rc_namespace, $row->rc_title )->getPrefixedText(),
				revid: (int)$row->rc_this_oldid,
				pageid: (int)$row->rc_cur_id,
				oldRevid: (int)$row->rc_last_oldid ?: null,
				user: $row->rc_user_text,
				timestamp: wfTimestamp( TimestampFormat::ISO_8601, $row->rc_timestamp ),
				newlen: (int)$row->rc_new_len,
				oldlen: (int)$row->rc_old_len,
				parsedcomment: $canSeeComment ? ( $comments[ $row->rc_id ] ?? '' ) : '',
				// TODO: page descriptions come from WikibaseClient's
				//   DescriptionLookup, which this endpoint does not reach yet.
				//   The client still gets the field, so nothing breaks; it has
				//   to be filled before the client stops fetching descriptions
				//   for itself.
				description: '',
				minor: (bool)$row->rc_minor,
				bot: (bool)$row->rc_bot,
				new: $row->rc_source === RecentChange::SRC_NEW,
				tags: $row->ts_tags ? explode( ',', $row->ts_tags ) : [],
			);
		}

		return $items;
	}

	/**
	 * Read a cursor this class wrote in RecentChangeFeedItem::getCursor().
	 *
	 * @param string|null $cursor
	 * @return array{0:string,1:int}|null Timestamp and recentchanges row id
	 */
	private function parseCursor( ?string $cursor ): ?array {
		if ( $cursor === null ) {
			return null;
		}

		$parts = explode( '|', $cursor, 2 );
		if ( count( $parts ) !== 2 || !ctype_digit( $parts[1] ) ) {
			return null;
		}

		// An unparseable timestamp comes back as false, not null, so test for
		// falsiness. The cursor arrives from the client, and a bad one must fall
		// back to the newest item rather than reach startAt() and fatal.
		$timestamp = wfTimestampOrNull( TimestampFormat::MW, $parts[0] );
		if ( !$timestamp ) {
			return null;
		}

		return [ $timestamp, (int)$parts[1] ];
	}
}
