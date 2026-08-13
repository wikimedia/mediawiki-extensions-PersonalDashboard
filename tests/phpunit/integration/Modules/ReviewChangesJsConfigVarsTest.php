<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Modules;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PersonalDashboard\Modules\ReviewChanges;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * End-to-end coverage of the recently-edited feed against a real database.
 *
 * The unit test mocks the query builders, so it can only prove the row mapping.
 * This one proves the query itself selects the right changes and that they reach
 * the JS config var the useRecentlyEditedFeed composable reads.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\ReviewChanges
 * @group Database
 */
class ReviewChangesJsConfigVarsTest extends MediaWikiIntegrationTestCase {

	/** Config var carrying the server-prefetched recently-edited feed. */
	private const string CONFIG_VAR = 'wgPersonalDashboardRecentlyEditedItems';

	private function newModule( User $viewer ): ReviewChanges {
		$services = $this->getServiceContainer();

		$context = new RequestContext();
		$context->setUser( $viewer );

		return new ReviewChanges(
			$context,
			$services->getConnectionProvider(),
			$services->getChangesListQueryFactory(),
			$services->getCommentFormatter()
		);
	}

	private function getFeedItems( User $viewer ): array {
		$configVars = $this->newModule( $viewer )->getJsConfigVars();
		$this->assertArrayHasKey(
			self::CONFIG_VAR,
			$configVars,
			'the feed must be exposed under the name useRecentlyEditedFeed.js reads'
		);
		return $configVars[ self::CONFIG_VAR ];
	}

	public function testSurfacesOtherEditorsChangesToPagesTheViewerEdited() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Shared Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Shared Page', 'someone else edited', 'their summary', NS_MAIN, $other );

		$items = $this->getFeedItems( $viewer );

		$this->assertCount( 1, $items );
		$this->assertSame( 'Shared Page', $items[0]['title'] );
		$this->assertSame( $other->getName(), $items[0]['user'] );
		$this->assertStringContainsString( 'their summary', $items[0]['parsedcomment'] );
	}

	public function testExcludesTheViewersOwnEdits() {
		$viewer = $this->getTestUser()->getUser();

		$this->editPage( 'Solo Page', 'first', '', NS_MAIN, $viewer );
		$this->editPage( 'Solo Page', 'second', '', NS_MAIN, $viewer );

		$this->assertSame( [], $this->getFeedItems( $viewer ) );
	}

	public function testExcludesPagesTheViewerNeverEdited() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		// The viewer needs at least one edit somewhere, otherwise the module
		// short-circuits before it ever runs the changes query.
		$this->editPage( 'Viewer Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Unrelated Page', 'nothing to do with the viewer', '', NS_MAIN, $other );

		$this->assertSame( [], $this->getFeedItems( $viewer ) );
	}

	public function testExcludesBotEdits() {
		$viewer = $this->getTestUser()->getUser();
		$bot = $this->getTestUser( [ 'bot' ] )->getUser();

		$this->editPage( 'Bot Target', 'viewer was here', '', NS_MAIN, $viewer );
		// editPage() can't flag an edit as a bot edit, and merely belonging to the
		// bot group does not set rc_bot — the edit itself has to be saved as one.
		$this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Bot Target' ) )
			->newPageUpdater( $bot )
			->setContent( SlotRecord::MAIN, new WikitextContent( 'beep boop' ) )
			->saveRevision( '', EDIT_FORCE_BOT );

		$this->assertSame( [], $this->getFeedItems( $viewer ) );
	}

	public function testReturnsNoItemsForAnAnonymousViewer() {
		$this->assertSame( [], $this->getFeedItems( new User() ) );
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

		$items = $this->getFeedItems( $viewer );

		// The change itself still belongs in the feed — only its summary is withheld.
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

		$items = $this->getFeedItems( $viewer );

		$this->assertCount( 1, $items );
		$this->assertStringContainsString( 'secret summary', $items[0]['parsedcomment'] );
	}

	public function testItemsCarryTheFieldsTheFeedItemShapeRequires() {
		$viewer = $this->getTestUser()->getUser();
		$other = $this->getTestUser( 'other' )->getUser();

		$this->editPage( 'Shaped Page', 'viewer was here', '', NS_MAIN, $viewer );
		$this->editPage( 'Shaped Page', 'a longer body of text', '', NS_MAIN, $other );

		$item = $this->getFeedItems( $viewer )[0];

		$this->assertSame( [
			'title', 'revid', 'pageid', 'old_revid', 'user', 'timestamp', 'newlen',
			'oldlen', 'parsedcomment', 'minor', 'bot', 'new', 'tags',
		], array_keys( $item ) );
		$this->assertSame(
			Title::newFromText( 'Shaped Page' )->getArticleID(),
			(int)$item['pageid']
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$item['timestamp'],
			'the composable sorts on this string, so it has to be ISO-8601'
		);
		$this->assertIsBool( $item['minor'] );
		$this->assertIsBool( $item['bot'] );
		$this->assertIsArray( $item['tags'] );
	}
}
