<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed\Source;

use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\Config\Config;
use MediaWiki\Extension\PersonalDashboard\Feed\ChangesListFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\IPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\IRevisionScoreLookup;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQueryFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\Search\SearchEngine;
use MediaWiki\Search\SearchEngineFactory;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\IWANCacheBuilder;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Edits to pages related to the ones the viewer edits most.
 *
 * The recentlyedited source shows the pages the viewer worked on. This one goes
 * one step further out: it takes the pages the viewer edits most and asks
 * CirrusSearch which pages are like them.
 *
 * The search goes through core's SearchEngine, so it makes no HTTP request. The
 * source contributes nothing without CirrusSearch, which owns the morelike
 * keyword.
 */
class MostEditedFeedSource extends ChangesListFeedSource {

	/** How many of the viewer's most edited pages to search with. */
	private const int SEED_PAGE_LIMIT = 5;

	private const int RELATED_PAGES_PER_SEED = 10;

	/**
	 * How many of the viewer's revisions to count edits over.
	 *
	 * No index covers rev_actor together with rev_page, so counting the edits
	 * for each page means reading the viewer's revisions one by one. Below the
	 * cap the counts cover every edit the viewer made. Above it they favour
	 * recent edits, which is closer to what the viewer works on now.
	 */
	private const int REVISION_SCAN_LIMIT = 1000;

	/**
	 * Ranking profile for the search. The default profile ranks on incoming
	 * links, which measures how popular a page is, not how related it is.
	 */
	private const string RESCORE_PROFILE = 'classic_noboostlinks';

	private int $revisionScanLimit;

	/**
	 * @inheritDoc
	 * @param array $options As for the parent, plus:
	 *   - revisionScanLimit: (int, default 1000) how many of the viewer's
	 *     revisions to count edits over. See REVISION_SCAN_LIMIT.
	 */
	public function __construct(
		ChangesListQueryFactory $changesListQueryFactory,
		IConnectionProvider $connectionProvider,
		RowCommentFormatter $rowCommentFormatter,
		Config $mainConfig,
		IRevisionScoreLookup $scoreLookup,
		IPageDescriptionLookup $pageDescriptionLookup,
		private readonly ActorNormalization $actorNormalization,
		private readonly SearchEngineFactory $searchEngineFactory,
		private readonly IWANCacheBuilder $cache,
		private readonly ExtensionRegistry $extensionRegistry,
		array $options = [],
	) {
		parent::__construct(
			$changesListQueryFactory,
			$connectionProvider,
			$rowCommentFormatter,
			$mainConfig,
			$scoreLookup,
			$pageDescriptionLookup,
			$options
		);

		$this->revisionScanLimit = $options['revisionScanLimit'] ?? self::REVISION_SCAN_LIMIT;
	}

	/** @inheritDoc */
	protected function applySourceFilters(
		ChangesListQuery $query,
		FeedRequest $request
	): bool {
		if ( !$this->extensionRegistry->isLoaded( 'CirrusSearch' ) ) {
			return false;
		}

		$user = $request->authority->getUser();
		if ( !$user->isRegistered() ) {
			return false;
		}

		$titles = [];
		foreach ( $this->getRelatedPages( $user ) as $pageName ) {
			$title = TitleValue::tryNew( NS_MAIN, $pageName );
			if ( $title !== null ) {
				$titles[] = $title;
			}
		}

		// requireTitle() adds no condition when it gets no titles, which would
		// return every recent change on the wiki.
		if ( $titles === [] ) {
			return false;
		}

		$query->requireLatest();
		foreach ( $titles as $title ) {
			$query->requireTitle( $title );
		}

		return true;
	}

	/**
	 * The scan and the searches cost too much to run on every page load, and
	 * the answer moves slowly. The edits themselves still come fresh from
	 * recentchanges, so only this page set ages.
	 *
	 * @return string[] Page names, as database keys
	 */
	private function getRelatedPages( UserIdentity $user ): array {
		return $this->cache->buildGetWithSetCallback()
			->key( 'personaldashboard', 'mostedited-related', $user->getId() )
			->keepForADay()
			->callback( function ( $oldValue, &$ttl ) use ( $user ) {
				$relatedPages = $this->fetchRelatedPages( $user );
				if ( $relatedPages === null ) {
					// A day would outlast a busy search cluster, and no cache at
					// all would make a wiki with no search backend wait for a
					// timeout on every page load.
					$ttl = ExpirationAwareness::TTL_MINUTE;

					return $oldValue ?: [];
				}

				return $relatedPages;
			} )
			->fetch();
	}

	/** @return string[]|null Page names, as database keys, or null if a search failed */
	private function fetchRelatedPages( UserIdentity $user ): ?array {
		$seedPages = $this->getMostEditedPages( $user );
		if ( $seedPages === [] ) {
			return [];
		}

		// One search for each seed page, rather than one search for all of them.
		// A single search divides its results between the seeds, and a long or
		// popular page then crowds out the others (T423871).
		$searchEngine = $this->newSearchEngine();

		$relatedPages = [];
		foreach ( $seedPages as $seedPage ) {
			$titles = $this->searchRelatedPages( $searchEngine, $seedPage );
			// Give up on the first failure. The remaining searches would meet
			// the same busy cluster.
			if ( $titles === null ) {
				return null;
			}

			foreach ( $titles as $title ) {
				// A list, not keys: PHP turns a numeric page name into an integer.
				$relatedPages[] = $title->getDBkey();
			}
		}

		// A seed can come back as a result for another seed, and the pages the
		// viewer edits belong to the recentlyedited source.
		return array_values( array_diff( array_unique( $relatedPages ), $seedPages ) );
	}

	/** @return string[] Page names, as database keys, most edited first */
	private function getMostEditedPages( UserIdentity $user ): array {
		$dbr = $this->connectionProvider->getReplicaDatabase();

		$actorId = $this->actorNormalization->findActorId( $user, $dbr );
		if ( !$actorId ) {
			return [];
		}

		$revisions = $dbr->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( 'revision' )
			->join( 'page', null, [ 'rev_page = page_id' ] )
			->where( [
				'rev_actor' => $actorId,
				'page_namespace' => NS_MAIN,
				// A redirect says nothing about what the viewer writes about.
				'page_is_redirect' => 0,
			] )
			// rev_timestamp counts whole seconds, and an editor can save many
			// times within one. rev_id decides those, and the query costs no
			// more for it: the rev_actor_timestamp index ends with rev_id.
			->orderBy( [ 'rev_timestamp', 'rev_id' ], SelectQueryBuilder::SORT_DESC )
			->limit( $this->revisionScanLimit );

		return $dbr->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( $revisions, 'scanned_revisions' )
			->groupBy( 'page_title' )
			->orderBy( 'COUNT(*)', SelectQueryBuilder::SORT_DESC )
			// Two pages with the same count would otherwise swap places between
			// regenerations, changing the whole feed for no visible reason.
			->orderBy( 'page_title' )
			->limit( self::SEED_PAGE_LIMIT )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	private function newSearchEngine(): SearchEngine {
		$searchEngine = $this->searchEngineFactory->create();
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setLimitOffset( self::RELATED_PAGES_PER_SEED );
		$searchEngine->setFeatureData(
			SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE,
			self::RESCORE_PROFILE
		);

		return $searchEngine;
	}

	/**
	 * @return Title[]|null Null if the search failed, which a busy search
	 *   cluster reports through the pool counter
	 */
	private function searchRelatedPages( SearchEngine $searchEngine, string $seedPage ): ?array {
		$matches = $searchEngine->searchText( 'morelike:' . $seedPage );
		if ( $matches instanceof Status ) {
			$matches = $matches->isOK() ? $matches->getValue() : null;
		}

		return $matches instanceof ISearchResultSet ? $matches->extractTitles() : null;
	}
}
