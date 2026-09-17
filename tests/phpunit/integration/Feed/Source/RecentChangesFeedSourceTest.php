<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed\Source;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\IPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\IRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\NullPageDescriptionLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\NullRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\Source\RecentChangesFeedSource;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * The shared ChangesListFeedSource behaviour, exercised through the plainest of
 * the three sources: the filters every source inherits, and the cursor.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\ChangesListFeedSource
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\Source\RecentChangesFeedSource
 * @group Database
 */
class RecentChangesFeedSourceTest extends MediaWikiIntegrationTestCase {

	private function newSource( array $options = [] ): RecentChangesFeedSource {
		$services = $this->getServiceContainer();

		$source = new RecentChangesFeedSource(
			$services->getChangesListQueryFactory(),
			$services->getConnectionProvider(),
			$services->getRowCommentFormatter(),
			$services->getMainConfig(),
			new NullRevisionScoreLookup(),
			new NullPageDescriptionLookup(),
			$options
		);
		$source->setName( 'recentchanges' );

		return $source;
	}

	/**
	 * Make sure a page exists, without the creation being visible to the feed.
	 *
	 * The sources ask for rc_source = SRC_EDIT, the way the client asked for
	 * rctype=edit, so a page creation never reaches the feed. Every fixture
	 * therefore needs a creation first, by a user no assertion looks at.
	 */
	private function seedPage( string $title, int $ns = NS_MAIN ): void {
		if ( !Title::makeTitle( $ns, $title )->exists() ) {
			$this->editPage(
				$title, 'created', '', $ns, $this->getTestUser( 'creator' )->getUser()
			);
		}
	}

	private function editPageAs( string $title, Authority $author, string $summary = '', int $ns = NS_MAIN ): void {
		$this->seedPage( $title, $ns );
		$this->editPage( $title, 'body ' . wfRandomString(), $summary, $ns, $author );
	}

	/**
	 * @return string[] Page titles, newest first
	 */
	private function getTitles( Authority $viewer, int $limit = 10, array $options = [] ): array {
		$result = $this->newSource( $options )->getItems( new FeedRequest( $viewer, $limit ) );

		return array_map( static fn ( $item ) => $item->toArray()['title'], $result->items );
	}

	public function testReturnsOtherPeoplesMainspaceEditsNewestFirst() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Alpha', $other );
		$this->editPageAs( 'Beta', $other );

		$this->assertSame( [ 'Beta', 'Alpha' ], $this->getTitles( $viewer ) );
	}

	public function testExcludesTheViewersOwnEdits() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Mine', $viewer );
		$this->editPageAs( 'Theirs', $other );

		$this->assertSame( [ 'Theirs' ], $this->getTitles( $viewer ) );
	}

	public function testKeepsTheViewersOwnEditsWhenPersonalizationIsOff() {
		// The switch a depersonalized Review Changes will register this class a
		// second time with. Without it, "no personalization" would still be
		// filtered by who is asking.
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Mine', $viewer );
		$this->editPageAs( 'Theirs', $other );

		$this->assertSame(
			[ 'Theirs', 'Mine' ],
			$this->getTitles( $viewer, 10, [ 'excludeSelf' => false ] )
		);
	}

	public function testExcludesBotEdits() {
		$viewer = $this->getTestUser()->getUser();
		$bot = $this->getTestUser( [ 'bot' ] )->getUser();

		$this->seedPage( 'Bot Page' );
		// editPage() cannot flag an edit as a bot edit; the edit itself has to be
		// saved as one for rc_bot to be set.
		$this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Bot Page' ) )
			->newPageUpdater( $bot )
			->setContent( SlotRecord::MAIN, new WikitextContent( 'beep boop' ) )
			->saveRevision( '', EDIT_FORCE_BOT );

		$this->assertSame( [], $this->getTitles( $viewer ) );
	}

	public function testExcludesEditsOutsideMainspace() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Talk Page', $other, '', NS_TALK );
		$this->editPageAs( 'Article', $other );

		$this->assertSame( [ 'Article' ], $this->getTitles( $viewer ) );
	}

	public function testReturnsOneRowPerPage() {
		// The client used to fetch every revision and drop repeated titles in
		// JavaScript, spending items out of a limit it had already paid for.
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Busy', $other );
		$this->editPageAs( 'Busy', $other );
		$this->editPageAs( 'Busy', $other );
		$this->editPageAs( 'Quiet', $other );

		$this->assertSame( [ 'Quiet', 'Busy' ], $this->getTitles( $viewer ) );
	}

	public function testReportsWhetherMoreItemsMayFollow() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'One', $other );
		$this->editPageAs( 'Two', $other );

		$source = $this->newSource();

		$this->assertTrue(
			$source->getItems( new FeedRequest( $viewer, 1 ) )->mayHaveMore,
			'a full page means another may follow'
		);
		$this->assertFalse(
			$source->getItems( new FeedRequest( $viewer, 10 ) )->mayHaveMore,
			'a short page means the source is exhausted'
		);
	}

	public function testTheCursorResumesWithoutOverlapOrGap() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		foreach ( [ 'P1', 'P2', 'P3', 'P4' ] as $title ) {
			$this->editPageAs( $title, $other );
		}

		$source = $this->newSource();

		$first = $source->getItems( new FeedRequest( $viewer, 2 ) );
		$this->assertCount( 2, $first->items );
		$this->assertTrue( $first->mayHaveMore );

		// The endpoint resumes from the last item it actually used.
		$cursor = $first->items[ array_key_last( $first->items ) ]->getCursor();
		$second = $source->getItems( new FeedRequest( $viewer, 2, $cursor ) );

		$firstTitles = array_map( static fn ( $i ) => $i->toArray()['title'], $first->items );
		$secondTitles = array_map( static fn ( $i ) => $i->toArray()['title'], $second->items );

		$this->assertSame( [ 'P4', 'P3' ], $firstTitles );
		$this->assertSame(
			[ 'P2', 'P1' ],
			$secondTitles,
			'the second page must start one row on, with nothing repeated and nothing skipped'
		);
	}

	public function testAMalformedCursorStartsFromTheNewestItem() {
		// The cursor reaches us from the client, so a broken one must not be a
		// fatal error. Falling back to the newest item is the safe reading.
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Only', $other );

		foreach ( [ 'nonsense', '|', '20260310120000|', 'notatimestamp|12' ] as $cursor ) {
			$result = $this->newSource()->getItems( new FeedRequest( $viewer, 10, $cursor ) );
			$this->assertCount( 1, $result->items, "cursor '$cursor' should not have thrown" );
		}
	}

	public function testAnAnonymousViewerDoesNotBreakTheQuery() {
		// The endpoint will turn anonymous requests away before they reach a
		// source, but this source has no viewer-specific join to stop it, so it
		// must not fall over if one ever arrives.
		$other = $this->getTestUser( 'other' )->getUser();
		$this->editPageAs( 'Public', $other );

		$this->assertSame( [ 'Public' ], $this->getTitles( new User() ) );
	}

	public function testScoresAreAttachedToTheItemTheyBelongTo() {
		// The lookup is keyed by revision id, and the base class asks it once for
		// the whole page. Getting the key wrong would silently put one edit's
		// score on another's card.
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Risky', $other );

		$scored = new class implements IRevisionScoreLookup {
			/** @var int[] */
			public array $asked = [];

			public function getScores( array $revIds ): array {
				$this->asked = $revIds;
				return array_fill_keys(
					$revIds,
					[ 'revertrisklanguageagnostic' => [ 'true' => 0.97, 'false' => 0.03 ] ]
				);
			}

			public function getHighRiskThreshold(): ?array {
				// The source attaches scores and never reads the threshold; the
				// card does that.
				return null;
			}
		};

		$services = $this->getServiceContainer();
		$source = new RecentChangesFeedSource(
			$services->getChangesListQueryFactory(),
			$services->getConnectionProvider(),
			$services->getRowCommentFormatter(),
			$services->getMainConfig(),
			$scored,
			new NullPageDescriptionLookup()
		);
		$source->setName( 'recentchanges' );

		$item = $source->getItems( new FeedRequest( $viewer, 10 ) )->items[0]->toArray();

		$this->assertSame( [ $item['revid'] ], $scored->asked );
		$this->assertSame(
			[ 'revertrisklanguageagnostic' => [ 'true' => 0.97, 'false' => 0.03 ] ],
			$item['oresscores']
		);
	}

	public function testDescriptionsAreAttachedToThePageTheyBelongTo() {
		// The lookup is keyed by page id while the score lookup is keyed by
		// revision id, and both are read in the same loop. Crossing them would
		// put one page's description on another's card.
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Described', $other );
		$this->editPageAs( 'Undescribed', $other );

		$described = new class implements IPageDescriptionLookup {
			/** @var array<int,string> */
			public array $asked = [];

			public function getDescriptions( array $pageIdentities ): array {
				foreach ( $pageIdentities as $page ) {
					$this->asked[$page->getId()] = $page->getDBkey();
				}

				// Only the page whose title says so, so the other item proves an
				// absent description becomes an empty string rather than a
				// neighbour's text.
				$describedId = array_search( 'Described', $this->asked, true );

				return $describedId === false ? [] : [ $describedId => 'A described page' ];
			}
		};

		$services = $this->getServiceContainer();
		$source = new RecentChangesFeedSource(
			$services->getChangesListQueryFactory(),
			$services->getConnectionProvider(),
			$services->getRowCommentFormatter(),
			$services->getMainConfig(),
			new NullRevisionScoreLookup(),
			$described
		);
		$source->setName( 'recentchanges' );

		$items = $source->getItems( new FeedRequest( $viewer, 10 ) )->items;
		$byTitle = [];
		foreach ( $items as $item ) {
			$byTitle[$item->toArray()['title']] = $item->toArray()['description'];
		}

		$this->assertSame( 'A described page', $byTitle['Described'] );
		$this->assertSame( '', $byTitle['Undescribed'] );
		$this->assertCount( 2, $described->asked, 'both pages should be looked up in one call' );
	}

	public function testItemsCarryTheShapeTheClientRenders() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Shaped', $other, 'my summary' );

		$source = $this->newSource();
		$item = $source->getItems( new FeedRequest( $viewer, 10 ) )->items[0]->toArray();

		$this->assertSame( [
			'id', 'feedorigin', 'title', 'revid', 'pageid', 'old_revid', 'user',
			'timestamp', 'newlen', 'oldlen', 'parsedcomment', 'description',
			'minor', 'bot', 'new', 'tags', 'oresscores',
		], array_keys( $item ) );
		// The schema is read only when the API specification is generated, so no
		// request ever notices a field added to the item and not to the schema.
		$this->assertEqualsCanonicalizing(
			array_keys( $item ),
			array_keys( $source->getItemSchema()['properties'] ),
			'every field the endpoint returns is a field it documents'
		);
		$this->assertSame( 'recentchanges', $item['feedorigin'] );
		$this->assertSame( 'recentchanges-' . $item['revid'], $item['id'] );
		$this->assertSame( $other->getName(), $item['user'] );
		$this->assertStringContainsString( 'my summary', $item['parsedcomment'] );
		$this->assertNotNull( $item['old_revid'], 'an edit has a parent revision' );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$item['timestamp'],
			'the merge orders on this string, so it has to be ISO-8601'
		);
	}
}
