<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed\Source;

use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\ChangesListFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQueryFactory;
use MediaWiki\User\ActorNormalization;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Other people's edits to pages the viewer edited recently.
 *
 * A page someone worked on is a page they can review with some context, so this
 * source turns the viewer's own edit history into a watchlist they never had to
 * curate.
 *
 * This is a port of ReviewChanges::getRecentlyEditedItems(), which prefetched
 * the same rows and shipped up to 100 of them in the page HTML. That config var
 * goes away once the client reads this endpoint instead.
 */
class RecentlyEditedFeedSource extends ChangesListFeedSource {

	/** How far back through the viewer's own edits to look for pages. */
	private const int RECENTLY_EDITED_PAGES_LIMIT = 50;

	public function __construct(
		ChangesListQueryFactory $changesListQueryFactory,
		IConnectionProvider $connectionProvider,
		RowCommentFormatter $rowCommentFormatter,
		Config $mainConfig,
		private readonly ActorNormalization $actorNormalization,
		array $options = [],
	) {
		parent::__construct(
			$changesListQueryFactory,
			$connectionProvider,
			$rowCommentFormatter,
			$mainConfig,
			$options
		);
	}

	/** @inheritDoc */
	protected function applySourceFilters(
		ChangesListQuery $query,
		FeedRequest $request
	): bool {
		$dbr = $this->connectionProvider->getReplicaDatabase();

		$actorId = $this->actorNormalization->findActorId( $request->authority->getUser(), $dbr );
		if ( !$actorId ) {
			return false;
		}

		$pagesRecentlyEdited = $dbr->newSelectQueryBuilder()
			->distinct()
			->select( 'rev_page' )
			->from( 'revision' )
			->join( 'page', null, [ 'rev_page = page_id' ] )
			->where( [
				'rev_actor' => $actorId,
				'page_namespace' => NS_MAIN,
			] )
			->orderBy( 'rev_timestamp DESC' )
			->limit( self::RECENTLY_EDITED_PAGES_LIMIT )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( !$pagesRecentlyEdited ) {
			return false;
		}

		$query->requireLatest()
			->where( $dbr->expr( 'rc_cur_id', '=', $pagesRecentlyEdited ) );

		return true;
	}
}
