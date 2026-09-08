<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed\Source;

use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\Source\WatchlistFeedSource;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * The watchlist source: recent edits to pages the viewer watches.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\Source\WatchlistFeedSource
 * @group Database
 */
class WatchlistFeedSourceTest extends MediaWikiIntegrationTestCase {

	private function newSource(): WatchlistFeedSource {
		$services = $this->getServiceContainer();

		$source = new WatchlistFeedSource(
			$services->getChangesListQueryFactory(),
			$services->getConnectionProvider(),
			$services->getRowCommentFormatter(),
			$services->getMainConfig()
		);
		$source->setName( 'watchlist' );

		return $source;
	}

	/**
	 * Edit a page that already exists.
	 *
	 * The source asks for rc_source = SRC_EDIT, so a page creation never reaches
	 * the feed and every fixture needs a creation first.
	 */
	private function editPageAs( string $title, Authority $author, string $summary = '' ): void {
		if ( !Title::newFromText( $title )->exists() ) {
			$this->editPage(
				$title, 'created', '', NS_MAIN, $this->getTestUser( 'creator' )->getUser()
			);
		}
		$this->editPage( $title, 'body ' . wfRandomString(), $summary, NS_MAIN, $author );
	}

	private function watch( Authority $viewer, string $title ): void {
		$this->getServiceContainer()->getWatchlistManager()
			->addWatch( $viewer, Title::newFromText( $title ) );
	}

	/**
	 * @return string[] Page titles, newest first
	 */
	private function getTitles( Authority $viewer, int $limit = 10 ): array {
		$result = $this->newSource()->getItems( new FeedRequest( $viewer, $limit ) );

		return array_map( static fn ( $item ) => $item->toArray()['title'], $result->items );
	}

	public function testReturnsOnlyEditsToWatchedPages() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Watched', $other );
		$this->editPageAs( 'Unwatched', $other );
		$this->watch( $viewer, 'Watched' );

		$this->assertSame( [ 'Watched' ], $this->getTitles( $viewer ) );
	}

	public function testStampsItemsWithTheSourceName() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Watched', $other );
		$this->watch( $viewer, 'Watched' );

		$item = $this->newSource()->getItems( new FeedRequest( $viewer, 10 ) )->items[0]->toArray();

		// The client keys its list on the id, and the same revision can reach it
		// from more than one source, so the name has to be in there.
		$this->assertSame( 'watchlist', $item['feedorigin'] );
		$this->assertSame( 'watchlist-' . $item['revid'], $item['id'] );
	}

	public function testExcludesTheViewersOwnEditsToWatchedPages() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Mine', $viewer );
		$this->editPageAs( 'Theirs', $other );
		$this->watch( $viewer, 'Mine' );
		$this->watch( $viewer, 'Theirs' );

		$this->assertSame( [ 'Theirs' ], $this->getTitles( $viewer ) );
	}

	public function testAnEmptyWatchlistYieldsNothing() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPageAs( 'Somewhere', $other );

		$this->assertSame( [], $this->getTitles( $viewer ) );
	}

	public function testAnAnonymousViewerYieldsNothingWithoutQuerying() {
		// An anonymous viewer has no watchlist, so the source says so rather than
		// asking the database a question with no possible answer.
		$result = $this->newSource()->getItems( new FeedRequest( new User(), 10 ) );

		$this->assertSame( [], $result->items );
		$this->assertFalse( $result->mayHaveMore );
	}
}
