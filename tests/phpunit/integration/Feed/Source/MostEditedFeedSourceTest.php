<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed\Source;

use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\NullPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\NullRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\Source\MostEditedFeedSource;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\Search\SearchEngine;
use MediaWiki\Search\SearchEngineFactory;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * The most edited source: edits to pages related to the ones the viewer edits most.
 *
 * The search itself belongs to CirrusSearch, so these cases stand a stub search
 * engine in for it and cover what this source owns: which pages it searches
 * with, which pages it keeps, and how long it remembers them.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\Source\MostEditedFeedSource
 * @group Database
 */
class MostEditedFeedSourceTest extends MediaWikiIntegrationTestCase {

	/** @var string[] Page name searched for, in the order the source asked */
	private array $searchedFor = [];

	/** @var string[][] Page names to return, keyed by the page name searched for */
	private array $relatedPages = [];

	/** Whether the search reports failure, as a busy cluster does */
	private bool $searchFails = false;

	/** Whether CirrusSearch is installed, which owns the morelike keyword */
	private bool $cirrusSearchLoaded = true;

	/** A search engine that answers `morelike:` from $this->relatedPages. */
	private function newSearchEngineFactory(): SearchEngineFactory {
		$searchEngine = $this->createMock( SearchEngine::class );
		$searchEngine->method( 'searchText' )->willReturnCallback(
			function ( string $term ) {
				$seedPage = substr( $term, strlen( 'morelike:' ) );
				$this->searchedFor[] = $seedPage;

				if ( $this->searchFails ) {
					return Status::newFatal( 'cirrussearch-too-busy-error' );
				}

				$titles = array_map(
					static fn ( string $pageName ) => Title::makeTitle( NS_MAIN, $pageName ),
					$this->relatedPages[ $seedPage ] ?? []
				);

				$resultSet = $this->createMock( ISearchResultSet::class );
				$resultSet->method( 'extractTitles' )->willReturn( $titles );

				return $resultSet;
			}
		);

		$searchEngineFactory = $this->createMock( SearchEngineFactory::class );
		$searchEngineFactory->method( 'create' )->willReturn( $searchEngine );

		return $searchEngineFactory;
	}

	private function newSource(
		?WANObjectCache $cache = null,
		array $options = []
	): MostEditedFeedSource {
		$services = $this->getServiceContainer();

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'CirrusSearch' )
			->willReturnCallback( fn (): bool => $this->cirrusSearchLoaded );

		$source = new MostEditedFeedSource(
			$services->getChangesListQueryFactory(),
			$services->getConnectionProvider(),
			$services->getRowCommentFormatter(),
			$services->getMainConfig(),
			new NullRevisionScoreLookup(),
			new NullPageDescriptionLookup(),
			$services->getActorNormalization(),
			$this->newSearchEngineFactory(),
			// Most cases need the searches they set up to actually run.
			$cache ?? WANObjectCache::newEmpty(),
			$extensionRegistry,
			$options
		);
		$source->setName( 'mostedited' );

		return $source;
	}

	/** @return array[] Items as the client sees them */
	private function getItems( Authority $viewer, int $limit = 10 ): array {
		$result = $this->newSource()->getItems( new FeedRequest( $viewer, $limit ) );

		return array_map( static fn ( $item ) => $item->toArray(), $result->items );
	}

	public function testStampsItemsWithTheSourceName() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->relatedPages = [ 'Viewer_Subject' => [ 'Related_Page' ] ];
		$this->editPage( 'Related Page', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Related Page', 'someone else edited', '', NS_MAIN, $other );

		$item = $this->getItems( $viewer )[0];

		// The client keys its list on the id, and the same revision can reach it
		// from more than one source, so the name has to be in there.
		$this->assertSame( 'mostedited', $item['feedorigin'] );
		$this->assertSame( 'mostedited-' . $item['revid'], $item['id'] );
	}

	public function testSearchesWithTheFiveMostEditedPagesOneAtATime() {
		$viewer = $this->getTestUser()->getUser();

		// Six pages, edited a descending number of times, so the page that
		// misses the cut is unambiguous.
		foreach ( [ 6, 5, 4, 3, 2, 1 ] as $index => $edits ) {
			for ( $edit = 1; $edit <= $edits; $edit++ ) {
				$this->editPage( "Subject $index", "revision $edit", '', NS_MAIN, $viewer );
			}
		}

		$this->getItems( $viewer );

		$this->assertSame(
			[ 'Subject_0', 'Subject_1', 'Subject_2', 'Subject_3', 'Subject_4' ],
			$this->searchedFor
		);
	}

	/**
	 * Only an article says what the viewer writes about. A redirect is a page
	 * name, and a talk page is a conversation, so neither seeds a search even
	 * when the viewer edits it more (T423871).
	 */
	public function testSeedsOnlyFromArticles() {
		$viewer = $this->getTestUser()->getUser();

		foreach ( [ 1, 2, 3 ] as $edit ) {
			$this->editPage(
				'A Redirect', "#REDIRECT [[Real Article]]\n<!-- $edit -->", '', NS_MAIN, $viewer
			);
			$this->editPage( 'Chatty Page', "comment $edit", '', NS_TALK, $viewer );
		}
		$this->editPage( 'Real Article', 'one edit', '', NS_MAIN, $viewer );

		$this->newSource()->getItems( new FeedRequest( $viewer, 10 ) );

		$this->assertSame( [ 'Real_Article' ], $this->searchedFor );
	}

	/**
	 * The cap is what keeps the count cheap for an editor with a long history,
	 * so it has to hold even when older pages carry more edits.
	 */
	public function testCountsOnlyTheMostRecentRevisions() {
		$viewer = $this->getTestUser()->getUser();

		// Three edits to the older page, two to the newer one. A cap of two
		// sees only the newer page.
		foreach ( [ 1, 2, 3 ] as $edit ) {
			$this->editPage( 'Old Favourite', "revision $edit", '', NS_MAIN, $viewer );
		}
		foreach ( [ 1, 2 ] as $edit ) {
			$this->editPage( 'Recent Work', "revision $edit", '', NS_MAIN, $viewer );
		}

		$source = $this->newSource( null, [ 'revisionScanLimit' => 2 ] );
		$source->getItems( new FeedRequest( $viewer, 10 ) );

		$this->assertSame( [ 'Recent_Work' ], $this->searchedFor );
	}

	/**
	 * The seed page has to reach the search as itself. A title may hold
	 * characters that search syntax uses, and escaping or trimming any of them
	 * would seed the search with a page name that nobody edited.
	 */
	public function testSearchesForTheSeedTitleExactly() {
		$viewer = $this->getTestUser()->getUser();

		$this->editPage( 'C* and "Free" Algebra?', 'the viewer wrote this', '', NS_MAIN, $viewer );

		$this->newSource()->getItems( new FeedRequest( $viewer, 10 ) );

		$this->assertSame( [ 'C*_and_"Free"_Algebra?' ], $this->searchedFor );
	}

	public function testBreaksATieOnTheMostEditedPagesByName() {
		$viewer = $this->getTestUser()->getUser();

		// Six pages, one edit each, so only the tie-break decides the five.
		foreach ( [ 'Foxtrot', 'Bravo', 'Echo', 'Alfa', 'Delta', 'Charlie' ] as $page ) {
			$this->editPage( $page, 'one edit', '', NS_MAIN, $viewer );
		}

		$this->newSource()->getItems( new FeedRequest( $viewer, 10 ) );

		$this->assertSame(
			[ 'Alfa', 'Bravo', 'Charlie', 'Delta', 'Echo' ],
			$this->searchedFor
		);
	}

	public function testIgnoresPagesTheViewerAlreadyEditsMost() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'First Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->editPage( 'Second Subject', 'and about this', '', NS_MAIN, $viewer );

		// Each seed page is the other one's closest relative.
		$this->relatedPages = [
			'First_Subject' => [ 'Second_Subject' ],
			'Second_Subject' => [ 'First_Subject' ],
		];

		$this->editPage( 'First Subject', 'someone else edited', '', NS_MAIN, $other );
		$this->editPage( 'Second Subject', 'someone else edited', '', NS_MAIN, $other );

		$this->assertSame( [], $this->getItems( $viewer ) );
	}

	public function testExcludesPagesTheSearchDidNotRelate() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->relatedPages = [ 'Viewer_Subject' => [ 'Related_Page' ] ];

		$this->editPage( 'Related Page', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Related Page', 'edited', '', NS_MAIN, $other );
		$this->editPage( 'Unrelated Page', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Unrelated Page', 'nothing to do with the viewer', '', NS_MAIN, $other );

		$items = $this->getItems( $viewer );

		$this->assertCount( 1, $items );
		$this->assertSame( 'Related Page', $items[0]['title'] );
	}

	/**
	 * A page name that PHP reads as an integer must stay a string, or
	 * TitleValue::tryNew() refuses it and the page falls out of the feed.
	 */
	public function testKeepsARelatedPageNameThatLooksLikeANumber() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->relatedPages = [ 'Viewer_Subject' => [ '0' ] ];

		$this->editPage( '0', 'created', '', NS_MAIN, $other );
		$this->editPage( '0', 'someone else edited', '', NS_MAIN, $other );
		$this->editPage( 'Unrelated Page', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Unrelated Page', 'nothing to do with the viewer', '', NS_MAIN, $other );

		$items = $this->getItems( $viewer );

		$this->assertCount( 1, $items );
		$this->assertSame( '0', $items[0]['title'] );
	}

	public function testReturnsNothingWhenTheSearchRelatesNothing() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->editPage( 'Somewhere Else', 'created', '', NS_MAIN, $other );

		$this->assertSame( [], $this->getItems( $viewer ) );
		$this->assertSame( [ 'Viewer_Subject' ], $this->searchedFor );
	}

	public function testDoesNotSearchForAViewerWhoHasNeverEdited() {
		$viewer = $this->getTestUser( 'newcomer' )->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Somewhere', 'created', '', NS_MAIN, $other );

		$this->assertSame( [], $this->getItems( $viewer ) );
		$this->assertSame( [], $this->searchedFor );
	}

	/**
	 * A busy search cluster is a passing condition, so a failure must not last
	 * the whole day the real answer would. It must still last a little, or a
	 * wiki with no search backend waits for a timeout on every page load.
	 */
	public function testHoldsAFailedSearchForOnlyAMinute() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->relatedPages = [ 'Viewer_Subject' => [ 'Related_Page' ] ];
		$this->editPage( 'Related Page', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Related Page', 'someone else edited', '', NS_MAIN, $other );

		$now = microtime( true );
		$store = new HashBagOStuff();
		$store->setMockTime( $now );
		$cache = new WANObjectCache( [ 'cache' => $store ] );
		$cache->setMockTime( $now );
		$request = new FeedRequest( $viewer, 10 );

		$this->searchFails = true;
		$this->assertSame(
			[],
			$this->newSource( $cache )->getItems( $request )->items,
			'a failed search contributes nothing'
		);

		$this->searchFails = false;
		$this->searchedFor = [];
		$this->assertSame(
			[],
			$this->newSource( $cache )->getItems( $request )->items,
			'the failure is remembered for a moment'
		);
		$this->assertSame( [], $this->searchedFor, 'so the next page load searches nothing' );

		$now += ExpirationAwareness::TTL_MINUTE + 1;
		$items = $this->newSource( $cache )->getItems( $request )->items;

		$this->assertCount( 1, $items, 'and the search runs again a minute later' );
		$this->assertSame( 'Related Page', $items[0]->toArray()['title'] );
	}

	public function testCachesAnEmptyResultTheSearchReallyReturned() {
		$viewer = $this->getTestUser()->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );

		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$request = new FeedRequest( $viewer, 10 );

		$this->newSource( $cache )->getItems( $request );
		$this->newSource( $cache )->getItems( $request );

		$this->assertSame(
			[ 'Viewer_Subject' ],
			$this->searchedFor,
			'the second request reuses the cached answer'
		);
	}

	public function testReturnsNothingForAnAnonymousViewer() {
		$this->assertSame( [], $this->getItems( new User() ) );
	}

	public function testContributesNothingWithoutCirrusSearch() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Viewer Subject', 'the viewer writes about this', '', NS_MAIN, $viewer );
		$this->relatedPages = [ 'Viewer_Subject' => [ 'Related_Page' ] ];
		$this->editPage( 'Related Page', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Related Page', 'someone else edited', '', NS_MAIN, $other );

		$this->cirrusSearchLoaded = false;

		$this->assertSame( [], $this->getItems( $viewer ) );
		$this->assertSame( [], $this->searchedFor );
	}
}
