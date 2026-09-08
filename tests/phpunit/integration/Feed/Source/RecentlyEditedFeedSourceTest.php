<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed\Source;

use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\Source\RecentlyEditedFeedSource;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * The recently edited source: other people's edits to pages the viewer worked on.
 *
 * These cases carry over from ReviewChangesJsConfigVarsTest, which covered the
 * same query while it still lived in the module and shipped its rows in the page
 * HTML.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\Source\RecentlyEditedFeedSource
 * @group Database
 */
class RecentlyEditedFeedSourceTest extends MediaWikiIntegrationTestCase {

	private function newSource(): RecentlyEditedFeedSource {
		$services = $this->getServiceContainer();

		$source = new RecentlyEditedFeedSource(
			$services->getChangesListQueryFactory(),
			$services->getConnectionProvider(),
			$services->getRowCommentFormatter(),
			$services->getMainConfig(),
			$services->getActorNormalization()
		);
		$source->setName( 'recentlyedited' );

		return $source;
	}

	/**
	 * @return array[] Items as the client sees them
	 */
	private function getItems( Authority $viewer, int $limit = 10 ): array {
		$result = $this->newSource()->getItems( new FeedRequest( $viewer, $limit ) );

		return array_map( static fn ( $item ) => $item->toArray(), $result->items );
	}

	public function testSurfacesOtherEditorsChangesToPagesTheViewerEdited() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Shared Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Shared Page', 'someone else edited', 'their summary', NS_MAIN, $other );

		$items = $this->getItems( $viewer );

		$this->assertCount( 1, $items );
		$this->assertSame( 'Shared Page', $items[0]['title'] );
		$this->assertSame( $other->getName(), $items[0]['user'] );
		$this->assertSame( 'recentlyedited', $items[0]['feedorigin'] );
		$this->assertStringContainsString( 'their summary', $items[0]['parsedcomment'] );
	}

	public function testExcludesTheViewersOwnEdits() {
		$viewer = $this->getTestUser()->getUser();

		$this->editPage( 'Solo Page', 'first', '', NS_MAIN, $viewer );
		$this->editPage( 'Solo Page', 'second', '', NS_MAIN, $viewer );

		$this->assertSame( [], $this->getItems( $viewer ) );
	}

	public function testExcludesPagesTheViewerNeverEdited() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		// The viewer needs an edit somewhere, or the source gives up before it
		// runs the changes query at all.
		$this->editPage( 'Viewer Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Unrelated Page', 'nothing to do with the viewer', '', NS_MAIN, $other );
		$this->editPage( 'Unrelated Page', 'still nothing', '', NS_MAIN, $other );

		$this->assertSame( [], $this->getItems( $viewer ) );
	}

	public function testReturnsNothingForAViewerWhoHasNeverEdited() {
		$viewer = $this->getTestUser( 'newcomer' )->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Somewhere', 'created', '', NS_MAIN, $other );
		$this->editPage( 'Somewhere', 'edited', '', NS_MAIN, $other );

		$this->assertSame( [], $this->getItems( $viewer ) );
	}

	public function testReturnsNothingForAnAnonymousViewer() {
		$this->assertSame( [], $this->getItems( new User() ) );
	}

	/**
	 * Hide the edit summary on every recentchanges row for a page, the way
	 * RevisionDelete does, without going through the whole RevDel machinery.
	 */
	private function hideEditSummaries( string $pageName, int $bitfield ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'recentchanges' )
			->set( [ 'rc_deleted' => $bitfield ] )
			->where( [
				'rc_namespace' => NS_MAIN,
				'rc_title' => str_replace( ' ', '_', $pageName ),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public function testWithholdsAnEditSummaryTheViewerMayNotSee() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Revdeleted Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Revdeleted Page', 'someone else edited', 'secret summary', NS_MAIN, $other );
		$this->hideEditSummaries( 'Revdeleted Page', RevisionRecord::DELETED_COMMENT );

		$items = $this->getItems( $viewer );

		// The change still belongs in the feed — only its summary is withheld.
		$this->assertCount( 1, $items );
		$this->assertSame( 'Revdeleted Page', $items[0]['title'] );
		$this->assertSame( '', $items[0]['parsedcomment'] );
	}

	public function testSurfacesAHiddenEditSummaryToAViewerWhoMaySeeIt() {
		$viewer = $this->getTestSysop()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Sysop Visible Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Sysop Visible Page', 'someone else edited', 'secret summary', NS_MAIN, $other );
		$this->hideEditSummaries( 'Sysop Visible Page', RevisionRecord::DELETED_COMMENT );

		$items = $this->getItems( $viewer );

		$this->assertCount( 1, $items );
		$this->assertStringContainsString( 'secret summary', $items[0]['parsedcomment'] );
	}
}
