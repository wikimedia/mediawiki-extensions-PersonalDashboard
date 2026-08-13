<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\ReviewChanges;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQueryFactory;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListResult;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * Unit coverage for the recently-edited feed the module prefetches server-side.
 *
 * getRecentlyEditedItems() is reached directly rather than through
 * getJsConfigVars(), because the latter consults ExtensionRegistry, which is
 * disabled in unit tests. That the result actually reaches the config var is
 * covered by ReviewChangesJsConfigVarsTest in the integration suite.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\ReviewChanges
 */
class ReviewChangesTest extends MediaWikiUnitTestCase {

	/**
	 * A recentchanges row in the shape ChangesListQuery hands back, with every
	 * integer column stringified the way the DB layer returns it.
	 */
	private function makeRow( array $overrides = [] ): array {
		return $overrides + [
			'rc_namespace' => '0',
			'rc_title' => 'Article_Title',
			'rc_this_oldid' => '3001',
			'rc_cur_id' => '77',
			'rc_last_oldid' => '3000',
			'rc_user_text' => 'Carol',
			'rc_timestamp' => '20240310120000',
			'rc_new_len' => '4000',
			'rc_old_len' => '3900',
			'rc_comment_text' => 'An edit summary',
			'rc_minor' => '0',
			'rc_bot' => '0',
			'rc_source' => 'mw.edit',
			'rc_deleted' => '0',
			'ts_tags' => null,
		];
	}

	/**
	 * @param int $actorId Actor ID of the viewing user; 0 for an anonymous user.
	 * @param array $pageIds Page IDs the user has recently edited.
	 * @param array[] $rows Recentchanges rows returned for those pages.
	 * @param ChangesListQueryFactory|null $factory Override to assert on query building.
	 * @param string[] $viewerRights Rights the viewing user holds, for rc_deleted checks.
	 */
	private function newModule(
		int $actorId = 42,
		array $pageIds = [ 77 ],
		array $rows = [],
		?ChangesListQueryFactory $factory = null,
		array $viewerRights = []
	): ReviewChanges {
		$user = $this->createMock( User::class );
		$user->method( 'getActorId' )->willReturn( $actorId );
		$user->method( 'isAllowedAny' )->willReturnCallback(
			static fn ( ...$rights ): bool => (bool)array_intersect( $rights, $viewerRights )
		);

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getConfig' )->willReturn( new HashConfig( [] ) );

		$commentFormatter = $this->createMock( CommentFormatter::class );
		// Tag the output so tests can prove comments are routed through the formatter
		// rather than passed through raw.
		$commentFormatter->method( 'format' )
			->willReturnCallback( static fn ( string $comment ): string => "<i>$comment</i>" );

		return new ReviewChanges(
			$context,
			$this->newConnectionProvider( $pageIds ),
			$factory ?? $this->newQueryFactory( $rows ),
			$commentFormatter
		);
	}

	/**
	 * A connection provider whose SelectQueryBuilder yields the given page IDs.
	 *
	 * @param array $pageIds
	 * @param ?IReadableDatabase $dbOverride Override to assert the query is skipped.
	 * @return IConnectionProvider
	 */
	private function newConnectionProvider(
		array $pageIds,
		?IReadableDatabase $dbOverride = null
	): IConnectionProvider {
		$db = $dbOverride ?? $this->createMock( IReadableDatabase::class );
		if ( !$dbOverride ) {
			$queryBuilder = $this->createMock( SelectQueryBuilder::class );
			foreach ( [ 'distinct', 'select', 'from', 'join', 'where', 'orderBy', 'limit', 'caller' ] as $method ) {
				$queryBuilder->method( $method )->willReturnSelf();
			}
			$queryBuilder->method( 'fetchFieldValues' )->willReturn( $pageIds );
			$db->method( 'newSelectQueryBuilder' )->willReturn( $queryBuilder );
		}

		return $this->createConfiguredMock( IConnectionProvider::class, [
			'getReplicaDatabase' => $db,
		] );
	}

	/**
	 * A ChangesListQueryFactory whose query yields the given recentchanges rows.
	 *
	 * @param array[] $rows
	 * @param ChangesListQuery|null $queryOverride Override to assert on the built query.
	 */
	private function newQueryFactory(
		array $rows,
		?ChangesListQuery $queryOverride = null
	): ChangesListQueryFactory {
		$query = $queryOverride ?? $this->createMock( ChangesListQuery::class );
		foreach ( [
			'audience', 'recentChangeFields', 'addChangeTagSummaryField', 'requireLatest',
			'excludeUser', 'excludeDeletedUser', 'excludeDeletedLogAction', 'excludeChangeTags',
			'where', 'orderBy', 'limit', 'caller',
		] as $method ) {
			$query->method( $method )->willReturnSelf();
		}
		$query->method( 'fetchResult' )->willReturn(
			$this->createConfiguredMock( ChangesListResult::class, [
				'getResultWrapper' => new FakeResultWrapper( $rows ),
			] )
		);

		return $this->createConfiguredMock( ChangesListQueryFactory::class, [
			'newQuery' => $query,
		] );
	}

	private function getFeedItems( ReviewChanges $module ): array {
		return TestingAccessWrapper::newFromObject( $module )->getRecentlyEditedItems();
	}

	public function testMapsRowToFeedItemFormat() {
		$module = $this->newModule( 42, [ 77 ], [ $this->makeRow() ] );

		$items = $this->getFeedItems( $module );

		$this->assertCount( 1, $items );
		$this->assertSame( [
			'title' => 'Article Title',
			'revid' => '3001',
			'pageid' => '77',
			'old_revid' => '3000',
			'user' => 'Carol',
			'timestamp' => '2024-03-10T12:00:00Z',
			'newlen' => '4000',
			'oldlen' => '3900',
			'parsedcomment' => '<i>An edit summary</i>',
			'minor' => false,
			'bot' => false,
			'new' => false,
			'tags' => [],
		], $items[0] );
	}

	public function testConvertsTimestampToIso8601() {
		$module = $this->newModule( 42, [ 77 ], [
			$this->makeRow( [ 'rc_timestamp' => '20250101083000' ] ),
		] );

		$this->assertSame( '2025-01-01T08:30:00Z', $this->getFeedItems( $module )[0]['timestamp'] );
	}

	public function testFlagsMinorEditsAsBooleans() {
		$module = $this->newModule( 42, [ 77 ], [
			$this->makeRow( [ 'rc_minor' => '1' ] ),
			$this->makeRow( [ 'rc_minor' => '0' ] ),
		] );

		$items = $this->getFeedItems( $module );

		// The JS side does Boolean( raw.minor ), which would treat the string '0' as
		// true, so these have to leave PHP already cast.
		$this->assertTrue( $items[0]['minor'] );
		$this->assertFalse( $items[1]['minor'] );
	}

	public function testSplitsChangeTagsOnComma() {
		$module = $this->newModule( 42, [ 77 ], [
			$this->makeRow( [ 'ts_tags' => 'mobile edit,visualeditor' ] ),
		] );

		$this->assertSame(
			[ 'mobile edit', 'visualeditor' ],
			$this->getFeedItems( $module )[0]['tags']
		);
	}

	public function testReturnsNoTagsForAnUntaggedRow() {
		$module = $this->newModule( 42, [ 77 ], [ $this->makeRow( [ 'ts_tags' => null ] ) ] );

		$this->assertSame( [], $this->getFeedItems( $module )[0]['tags'] );
	}

	public function testMapsEveryRow() {
		$module = $this->newModule( 42, [ 77, 78 ], [
			$this->makeRow( [ 'rc_this_oldid' => '1', 'rc_title' => 'First' ] ),
			$this->makeRow( [ 'rc_this_oldid' => '2', 'rc_title' => 'Second' ] ),
		] );

		$items = $this->getFeedItems( $module );

		$this->assertCount( 2, $items );
		$this->assertSame( [ 'First', 'Second' ], array_column( $items, 'title' ) );
	}

	public function testReturnsNoItemsWhenThereAreNoMatchingChanges() {
		$module = $this->newModule( 42, [ 77 ], [] );

		$this->assertSame( [], $this->getFeedItems( $module ) );
	}

	public function testSkipsQueriesForAnAnonymousUser() {
		$db = $this->createNoOpMock( IReadableDatabase::class );
		$factory = $this->createNoOpMock( ChangesListQueryFactory::class );

		$user = $this->createMock( User::class );
		$user->method( 'getActorId' )->willReturn( 0 );
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getConfig' )->willReturn( new HashConfig( [] ) );

		$module = new ReviewChanges(
			$context,
			$this->newConnectionProvider( [], $db ),
			$factory,
			$this->createNoOpMock( CommentFormatter::class )
		);

		$this->assertSame( [], $this->getFeedItems( $module ) );
	}

	public function testSkipsTheChangesQueryWhenTheUserHasNoRecentEdits() {
		$module = $this->newModule(
			42,
			[],
			[],
			$this->createNoOpMock( ChangesListQueryFactory::class )
		);

		$this->assertSame( [], $this->getFeedItems( $module ) );
	}

	public function testRunsTheChangesQueryOnlyOncePerRender() {
		$factory = $this->createMock( ChangesListQueryFactory::class );
		$factory->expects( $this->once() )
			->method( 'newQuery' )
			->willReturn( $this->newQueryFactory( [] )->newQuery() );

		$module = $this->newModule( 42, [ 77 ], [], $factory );

		$this->getFeedItems( $module );
	}

	public function testHidesAnEditSummaryTheViewerMayNotSee() {
		$module = $this->newModule( 42, [ 77 ], [
			$this->makeRow( [ 'rc_deleted' => (string)RevisionRecord::DELETED_COMMENT ] ),
		] );

		// An empty string rather than '<i></i>' proves the formatter was bypassed
		// entirely, so the hidden summary never reaches the client.
		$this->assertSame( '', $this->getFeedItems( $module )[0]['parsedcomment'] );
	}

	public function testShowsAHiddenEditSummaryToAViewerWhoMaySeeDeletedHistory() {
		$module = $this->newModule(
			42,
			[ 77 ],
			[ $this->makeRow( [ 'rc_deleted' => (string)RevisionRecord::DELETED_COMMENT ] ) ],
			null,
			[ 'deletedhistory' ]
		);

		$this->assertSame(
			'<i>An edit summary</i>',
			$this->getFeedItems( $module )[0]['parsedcomment']
		);
	}

	public function testHidesASuppressedEditSummaryFromAViewerWithOnlyDeletedHistory() {
		$module = $this->newModule(
			42,
			[ 77 ],
			[ $this->makeRow( [
				'rc_deleted' => (string)( RevisionRecord::DELETED_COMMENT | RevisionRecord::DELETED_RESTRICTED ),
			] ) ],
			null,
			[ 'deletedhistory' ]
		);

		$this->assertSame( '', $this->getFeedItems( $module )[0]['parsedcomment'] );
	}

	public function testShowsASuppressedEditSummaryToAViewerWhoMaySeeSuppressedRevisions() {
		$module = $this->newModule(
			42,
			[ 77 ],
			[ $this->makeRow( [
				'rc_deleted' => (string)( RevisionRecord::DELETED_COMMENT | RevisionRecord::DELETED_RESTRICTED ),
			] ) ],
			null,
			[ 'suppressrevision' ]
		);

		$this->assertSame(
			'<i>An edit summary</i>',
			$this->getFeedItems( $module )[0]['parsedcomment']
		);
	}

	public function testKeepsTheSummaryWhenOnlyOtherFieldsAreHidden() {
		$module = $this->newModule( 42, [ 77 ], [
			$this->makeRow( [ 'rc_deleted' => (string)RevisionRecord::DELETED_TEXT ] ),
		] );

		$this->assertSame(
			'<i>An edit summary</i>',
			$this->getFeedItems( $module )[0]['parsedcomment']
		);
	}

	public function testExcludesUnwantedEdits() {
		$query = $this->createMock( ChangesListQuery::class );
		$query->expects( $this->once() )->method( 'excludeUser' )->willReturnSelf();
		$query->expects( $this->once() )
			->method( 'excludeChangeTags' )
			->with( [ 'mw-reverted', 'mw-undo', 'mw-rollback' ] )
			->willReturnSelf();
		$query->expects( $this->once() )->method( 'requireLatest' )->willReturnSelf();

		$module = $this->newModule( 42, [ 77 ], [], $this->newQueryFactory( [], $query ) );

		$this->getFeedItems( $module );
	}
}
