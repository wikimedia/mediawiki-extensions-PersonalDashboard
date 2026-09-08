<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\RecentChangeFeedItem;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\RecentChangeFeedItem
 */
class RecentChangeFeedItemTest extends MediaWikiUnitTestCase {

	private function newItem( array $overrides = [] ): RecentChangeFeedItem {
		$fields = array_merge( [
			'source' => 'watchlist',
			'rcId' => 4711,
			'title' => 'Jupiter',
			'revid' => 1001,
			'pageid' => 77,
			'oldRevid' => 1000,
			'user' => 'Carol',
			'timestamp' => '2026-03-10T12:00:00Z',
			'newlen' => 4000,
			'oldlen' => 3900,
			'parsedcomment' => 'fixed a <b>typo</b>',
			'description' => 'Fifth planet from the Sun',
			'minor' => false,
			'bot' => false,
			'new' => false,
			'tags' => [ 'mw-manual-revert' ],
		], $overrides );

		return new RecentChangeFeedItem( ...$fields );
	}

	public function testIdCombinesSourceAndRevision() {
		// The client keys its list on the id, and two sources can legitimately
		// return the same revision, so the source name has to be part of it.
		$this->assertSame( 'watchlist-1001', $this->newItem()->getId() );
		$this->assertSame(
			'recentchanges-1001',
			$this->newItem( [ 'source' => 'recentchanges' ] )->getId()
		);
	}

	public function testCursorBreaksTiesOnTheRowId() {
		$this->assertSame( '2026-03-10T12:00:00Z|4711', $this->newItem()->getCursor() );
	}

	public function testTimestampIsReturnedUnchanged() {
		$this->assertSame( '2026-03-10T12:00:00Z', $this->newItem()->getTimestamp() );
	}

	public function testToArrayMatchesTheClientFeedItemShape() {
		// These keys are the contract the Vue card reads. Renaming one is a
		// breaking change for every consumer, so pin the whole shape.
		$this->assertSame( [
			'id' => 'watchlist-1001',
			'feedorigin' => 'watchlist',
			'title' => 'Jupiter',
			'revid' => 1001,
			'pageid' => 77,
			'old_revid' => 1000,
			'user' => 'Carol',
			'timestamp' => '2026-03-10T12:00:00Z',
			'newlen' => 4000,
			'oldlen' => 3900,
			'parsedcomment' => 'fixed a <b>typo</b>',
			'description' => 'Fifth planet from the Sun',
			'minor' => false,
			'bot' => false,
			'new' => false,
			'tags' => [ 'mw-manual-revert' ],
		], $this->newItem()->toArray() );
	}

	public function testAPageCreationHasNoParentRevision() {
		$item = $this->newItem( [ 'oldRevid' => null, 'new' => true, 'oldlen' => 0 ] );

		$this->assertNull( $item->toArray()['old_revid'] );
		$this->assertTrue( $item->toArray()['new'] );
	}

	public function testRowIdStaysOutOfThePayload() {
		// The row id is a pagination detail. The client has no use for it, so it
		// does not go over the wire.
		$this->assertArrayNotHasKey( 'rcId', $this->newItem()->toArray() );
		$this->assertArrayNotHasKey( 'rc_id', $this->newItem()->toArray() );
	}
}
