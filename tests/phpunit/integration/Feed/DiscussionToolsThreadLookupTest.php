<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\DiscussionToolsThreadLookup;
use MediaWiki\Page\PageIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * The only path in this extension that goes through DiscussionTools' own
 * parser: what counts as a thread, and how it is counted.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\DiscussionToolsThreadLookup
 * @group Database
 */
class DiscussionToolsThreadLookupTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'DiscussionTools' );
	}

	private function newLookup(): DiscussionToolsThreadLookup {
		$services = $this->getServiceContainer();

		return new DiscussionToolsThreadLookup(
			$services->getPageStore(),
			$services->getRevisionLookup(),
			$services->getParserOutputAccess(),
			new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
			$services->getStatsFactory(),
			$services->get( 'PersonalDashboardLogger' ),
		);
	}

	public function testSkipsPagesThatAreNotDiscussionPages(): void {
		$this->editPage( 'Not A Discussion Page', 'plain article content' );
		$page = $this->getServiceContainer()->getPageStore()->getPageByText( 'Not A Discussion Page' );

		$result = $this->newLookup()->getThreadsForPage( $page );

		$this->assertSame( [], $result->threads );
		$this->assertTrue( $result->complete );
	}

	public function testReturnsAnEmptyCompleteResultForANonExistentPage(): void {
		$page = PageIdentityValue::localIdentity( 0, NS_TALK, 'Does_Not_Exist_Anywhere' );

		$result = $this->newLookup()->getThreadsForPage( $page );

		$this->assertSame( [], $result->threads );
		$this->assertTrue( $result->complete );
	}

	/**
	 * A sub-heading is a thread of its own and part of the one above it, and
	 * the counts on both say what the talk page says under that heading.
	 */
	public function testCountsASubHeadingUnderItselfAndUnderItsTopic(): void {
		$alice = $this->getTestUser( 'alice' )->getUser();
		$bob = $this->getTestUser( 'bob' )->getUser();

		$wikitext = "== RfC: should we do the thing ==\n" .
			"Here's my proposal. --[[User:{$alice->getName()}]] 00:00, 1 January 2024 (UTC)\n\n" .
			"=== Survey ===\n" .
			"Support. --[[User:{$bob->getName()}]] 00:00, 2 January 2024 (UTC)";

		$this->editPage( 'Talk:Grouping fixture', $wikitext, '', NS_MAIN, $alice );
		// DiscussionTools prewarms the parser cache from a deferred update; the
		// test harness discards deferred updates unless asked to run them.
		$this->runDeferredUpdates();

		$page = $this->getServiceContainer()->getPageStore()->getPageByText( 'Talk:Grouping fixture' );
		$result = $this->newLookup()->getThreadsForPage( $page );

		$this->assertTrue( $result->complete );
		$this->assertSame(
			[ 'RfC: should we do the thing', 'Survey' ],
			array_map( static fn ( $thread ) => $thread->section, $result->threads )
		);

		[ $rfc, $survey ] = $result->threads;
		$this->assertSame( 2, $rfc->commentCount );
		$this->assertSame( 2, $rfc->authorCount );
		$this->assertNull( $rfc->topic );
		$this->assertSame( 1, $survey->commentCount );
		$this->assertSame( 1, $survey->authorCount );
		$this->assertSame( 'RfC: should we do the thing', $survey->topic );
	}

	/**
	 * A sub-thread names its topic, not whichever sub-heading happens to sit
	 * directly above it.
	 */
	public function testNamesTheOutermostHeadingAsTheTopic(): void {
		$alice = $this->getTestUser( 'alice' )->getUser();

		$wikitext = "== RfC: should we do the thing ==\n" .
			"=== Survey ===\n" .
			"==== Oppose ====\n" .
			"No. --[[User:{$alice->getName()}]] 00:00, 1 January 2024 (UTC)";

		$this->editPage( 'Talk:Nesting fixture', $wikitext, '', NS_MAIN, $alice );
		$this->runDeferredUpdates();

		$page = $this->getServiceContainer()->getPageStore()->getPageByText( 'Talk:Nesting fixture' );
		$result = $this->newLookup()->getThreadsForPage( $page );

		$this->assertTrue( $result->complete );

		$topicsBySection = [];
		foreach ( $result->threads as $thread ) {
			$topicsBySection[ $thread->section ] = $thread->topic;
		}

		$this->assertSame( [
			'RfC: should we do the thing' => null,
			'Survey' => 'RfC: should we do the thing',
			'Oppose' => 'RfC: should we do the thing',
		], $topicsBySection );
	}
}
