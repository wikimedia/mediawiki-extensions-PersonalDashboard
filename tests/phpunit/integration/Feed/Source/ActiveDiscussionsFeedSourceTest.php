<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed\Source;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\PersonalDashboard\Feed\DiscussionThread;
use MediaWiki\Extension\PersonalDashboard\Feed\DiscussionThreadResult;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\IDiscussionThreadLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\Source\ActiveDiscussionsFeedSource;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\PageStore;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * The Active Discussions source: which threads it keeps, the order it puts them
 * in, and how it resumes.
 *
 * DiscussionTools itself is behind the thread lookup, which has its own suite,
 * so these cases fake the lookup and cover what the source owns. The page store
 * is mocked too, which is what keeps them off the database.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\Source\ActiveDiscussionsFeedSource
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\DiscussionThreadFeedItem
 * @group PersonalDashboard
 */
class ActiveDiscussionsFeedSourceTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	/** Configured page name to page id, namespace and database key. */
	private const array PAGES = [
		'Talk:Noticeboard' => [ 22, NS_TALK, 'Noticeboard' ],
		'Talk:Village pump' => [ 11, NS_TALK, 'Village_pump' ],
		'Project:Village pump' => [ 33, NS_PROJECT, 'Village_pump' ],
	];

	private const array TALK_PAGES = [ 'Talk:Noticeboard', 'Talk:Village pump' ];

	/** @var array<string,DiscussionThreadResult> Threads by page database key */
	private array $threadsByPage = [];

	/** @var string[] Database keys of pages whose lookup throws */
	private array $failingPages = [];

	private function newSource( array $configuredPages ): ActiveDiscussionsFeedSource {
		$pageStore = $this->createMock( PageStore::class );
		$pageStore->method( 'getPageByText' )->willReturnCallback(
			static function ( string $text ): ?PageIdentityValue {
				// The real store parses the title first, so an underscore and a
				// space reach the same page.
				$name = strtr( $text, '_', ' ' );
				if ( !isset( self::PAGES[ $name ] ) ) {
					return null;
				}

				[ $pageId, $namespace, $dbKey ] = self::PAGES[ $name ];

				return PageIdentityValue::localIdentity( $pageId, $namespace, $dbKey );
			}
		);

		$lookup = $this->createMock( IDiscussionThreadLookup::class );
		$lookup->method( 'getThreadsForPage' )->willReturnCallback(
			function ( $page ): DiscussionThreadResult {
				if ( in_array( $page->getDBkey(), $this->failingPages, true ) ) {
					throw new RuntimeException( 'the lookup fell over' );
				}

				return $this->threadsByPage[ $page->getDBkey() ]
					?? DiscussionThreadResult::newEmpty();
			}
		);

		$source = new ActiveDiscussionsFeedSource(
			new HashConfig( [ 'PersonalDashboardActiveDiscussionsPages' => $configuredPages ] ),
			$pageStore,
			$lookup,
			new NullLogger(),
		);
		$source->setName( 'activediscussions' );

		return $source;
	}

	private static function newThread(
		string $itemId,
		string $timestamp,
		int $authorCount = 2
	): DiscussionThread {
		return new DiscussionThread(
			itemId: $itemId,
			section: 'Section ' . $itemId,
			anchor: 'Section_' . $itemId,
			topic: null,
			commentCount: 5,
			authorCount: $authorCount,
			latestReplyTimestamp: $timestamp,
			latestReplyId: 'c-' . $itemId,
		);
	}

	private function newRequest( int $limit, ?string $cursor = null ): FeedRequest {
		return new FeedRequest( $this->mockRegisteredUltimateAuthority(), $limit, $cursor );
	}

	/** @return string[] */
	private static function idsOf( array $items ): array {
		return array_map( static fn ( $item ) => $item->getId(), $items );
	}

	public function testDropsAThreadNobodyHasAnsweredYet(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-answered', '2026-03-10T12:00:00Z', 3 ),
			self::newThread( 'h-unanswered', '2026-03-11T12:00:00Z', 1 ),
		], true );

		$result = $this->newSource( [ 'Talk:Village pump' ] )->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [ 'activediscussions-11-h-answered' ], self::idsOf( $result->items ) );
		$this->assertFalse( $result->mayHaveMore );
	}

	/**
	 * Signatures carry whole minutes, so threads sharing a timestamp is the
	 * ordinary case and both tiebreakers have to decide it.
	 */
	public function testOrdersByNewestReplyThenPageThenHeading(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-b', '2026-03-11T12:00:00Z' ),
			self::newThread( 'h-a', '2026-03-11T12:00:00Z' ),
		], true );
		$this->threadsByPage['Noticeboard'] = new DiscussionThreadResult( [
			self::newThread( 'h-c', '2026-03-10T09:00:00Z' ),
			self::newThread( 'h-z', '2026-03-11T12:00:00Z' ),
		], true );

		$result = $this->newSource( self::TALK_PAGES )->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [
			'activediscussions-11-h-a',
			'activediscussions-11-h-b',
			'activediscussions-22-h-z',
			'activediscussions-22-h-c',
		], self::idsOf( $result->items ) );
	}

	/**
	 * An item id is built from heading text and author names, so it can carry
	 * the separator the cursor packs it with.
	 */
	public function testResumesPastAnItemIdHoldingTheCursorSeparator(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-A|B', '2026-03-11T12:00:00Z' ),
			self::newThread( 'h-C', '2026-03-11T12:00:00Z' ),
		], true );

		$source = $this->newSource( [ 'Talk:Village pump' ] );

		$firstPage = $source->getItems( $this->newRequest( 1 ) );
		$this->assertSame( [ 'activediscussions-11-h-A|B' ], self::idsOf( $firstPage->items ) );
		$this->assertTrue( $firstPage->mayHaveMore );

		$cursor = $firstPage->items[0]->getCursor();
		$this->assertSame( '2026-03-11T12:00:00Z|11|h-A%7CB', $cursor );

		$secondPage = $source->getItems( $this->newRequest( 1, $cursor ) );
		$this->assertSame( [ 'activediscussions-11-h-C' ], self::idsOf( $secondPage->items ) );
		$this->assertFalse( $secondPage->mayHaveMore );
	}

	/**
	 * A project page and its talk page share a database key, and a notice
	 * cross-posted to both gives each the same heading id. The lookup fake keys
	 * threads by database key, so both pages get the same thread here.
	 */
	public function testResumesBetweenPagesThatShareADatabaseKey(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-notice', '2026-03-11T12:00:00Z' ),
		], true );

		$source = $this->newSource( [ 'Talk:Village pump', 'Project:Village pump' ] );

		$firstPage = $source->getItems( $this->newRequest( 1 ) );
		$secondPage = $source->getItems(
			$this->newRequest( 1, $firstPage->items[0]->getCursor() )
		);

		$this->assertSame( [
			'activediscussions-11-h-notice',
			'activediscussions-33-h-notice',
		], array_merge( self::idsOf( $firstPage->items ), self::idsOf( $secondPage->items ) ) );
	}

	/**
	 * Over a list that holds still. A thread gaining a reply mid-walk sorts
	 * above the cursor and is lost to that walk, which the source says out loud
	 * rather than defending against.
	 */
	public function testWalksTheWholeListInOrderAcrossTwoPages(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-a', '2026-03-13T12:00:00Z' ),
			self::newThread( 'h-b', '2026-03-12T12:00:00Z' ),
		], true );
		$this->threadsByPage['Noticeboard'] = new DiscussionThreadResult( [
			self::newThread( 'h-c', '2026-03-11T12:00:00Z' ),
			self::newThread( 'h-d', '2026-03-10T12:00:00Z' ),
		], true );

		$source = $this->newSource( self::TALK_PAGES );

		$firstPage = $source->getItems( $this->newRequest( 2 ) );
		$secondPage = $source->getItems(
			$this->newRequest( 2, $firstPage->items[1]->getCursor() )
		);

		$this->assertSame( [
			'activediscussions-11-h-a',
			'activediscussions-11-h-b',
			'activediscussions-22-h-c',
			'activediscussions-22-h-d',
		], array_merge( self::idsOf( $firstPage->items ), self::idsOf( $secondPage->items ) ) );
		$this->assertFalse( $secondPage->mayHaveMore );
	}

	/**
	 * A degraded page makes the answer short, but not in a way the next page
	 * would fix, so it must not offer one.
	 */
	public function testPromisesNothingMoreForAPageThatCouldNotBeRead(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-a', '2026-03-11T12:00:00Z' ),
		], true );
		$this->threadsByPage['Noticeboard'] = DiscussionThreadResult::newDegraded();

		$result = $this->newSource( self::TALK_PAGES )->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [ 'activediscussions-11-h-a' ], self::idsOf( $result->items ) );
		$this->assertFalse( $result->mayHaveMore );
	}

	public function testKeepsTheOtherPagesWhenOneOfThemThrows(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-a', '2026-03-11T12:00:00Z' ),
		], true );
		$this->failingPages[] = 'Noticeboard';

		$result = $this->newSource( self::TALK_PAGES )->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [ 'activediscussions-11-h-a' ], self::idsOf( $result->items ) );
		$this->assertFalse( $result->mayHaveMore );
	}

	public function testSkipsAPageTheViewerMayNotRead(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-a', '2026-03-11T12:00:00Z' ),
		], true );

		$result = $this->newSource( [ 'Talk:Village pump' ] )->getItems( new FeedRequest(
			$this->mockRegisteredAuthorityWithoutPermissions( [ 'read' ] ),
			10
		) );

		$this->assertSame( [], $result->items );
		$this->assertFalse( $result->mayHaveMore );
	}

	public function testSkipsAPageNameThePageStoreCannotResolve(): void {
		$result = $this->newSource( [ '<not a title>' ] )->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [], $result->items );
		$this->assertFalse( $result->mayHaveMore );
	}

	/**
	 * Two names reaching one page would otherwise emit every thread twice,
	 * under one id each time, since a thread has no dedup key for the merge to
	 * collapse it on.
	 */
	public function testReadsAPageOnceWhenTwoNamesReachIt(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-a', '2026-03-11T12:00:00Z' ),
		], true );

		$result = $this->newSource( [ 'Talk:Village pump', 'Talk:Village_pump' ] )
			->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [ 'activediscussions-11-h-a' ], self::idsOf( $result->items ) );
	}

	/** The client is written against these field names. */
	public function testTheItemCarriesTheFieldsTheCardRenders(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			new DiscussionThread(
				itemId: 'h-Survey-20260311120000',
				section: 'Survey',
				anchor: 'Survey',
				topic: 'RfC: should we do the thing',
				commentCount: 12,
				authorCount: 4,
				latestReplyTimestamp: '2026-03-11T12:00:00Z',
				latestReplyId: 'c-Carol-20260311120000',
			),
		], true );

		$result = $this->newSource( [ 'Talk:Village pump' ] )->getItems( $this->newRequest( 10 ) );

		$this->assertSame( [
			'id' => 'activediscussions-11-h-Survey-20260311120000',
			'feedorigin' => 'activediscussions',
			'title' => 'Talk:Village pump',
			'pageid' => 11,
			'timestamp' => '2026-03-11T12:00:00Z',
			'section' => 'Survey',
			'anchor' => 'Survey',
			'topic' => 'RfC: should we do the thing',
			'commentCount' => 12,
			'authorCount' => 4,
			'latestReplyId' => 'c-Carol-20260311120000',
		], $result->items[0]->toArray() );

		// One thread among many on a page is already unique, so the merge never
		// drops one for another source's sake.
		$this->assertNull( $result->items[0]->getDedupKey() );
	}

	public function testDescribesEveryFieldItsItemsReturn(): void {
		$this->threadsByPage['Village_pump'] = new DiscussionThreadResult( [
			self::newThread( 'h-a', '2026-03-11T12:00:00Z' ),
		], true );

		$source = $this->newSource( [ 'Talk:Village pump' ] );
		$item = $source->getItems( $this->newRequest( 10 ) )->items[0];

		$this->assertSame(
			array_keys( $item->toArray() ),
			array_keys( $source->getItemSchema()['properties'] )
		);
	}
}
