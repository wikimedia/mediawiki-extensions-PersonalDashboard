<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use DateTimeImmutable;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedMerger;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedSourceResult;
use MediaWiki\Extension\PersonalDashboard\Feed\IFeedItem;
use MediaWikiUnitTestCase;

/**
 * The first thirteen cases are ports of the selectEvenlyAcrossFeeds() tests in
 * tests/vitest/ext.personalDashboard.reviewChanges/utils/feedHelpers.test.mjs,
 * one for one. Both implementations exist until the client stops merging for
 * itself, and these prove they agree.
 *
 * The rest cover what the PHP version adds: the cursors and the exhausted flags
 * the endpoint needs to ask for the next page.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\FeedMerger
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\FeedMergeResult
 */
class FeedMergerTest extends MediaWikiUnitTestCase {

	private function item( string $source, ?string $key, string $timestamp ): IFeedItem {
		return new class( $source, $key, $timestamp ) implements IFeedItem {
			public function __construct(
				private readonly string $source,
				private readonly ?string $key,
				private readonly string $timestamp,
			) {
			}

			public function getId(): string {
				return $this->source . '-' . $this->key;
			}

			public function getTimestamp(): string {
				return $this->timestamp;
			}

			public function getCursor(): string {
				return $this->timestamp . '|' . $this->key;
			}

			public function getDedupKey(): ?string {
				return $this->key;
			}

			public function toArray(): array {
				return [ 'source' => $this->source, 'key' => $this->key ];
			}
		};
	}

	/**
	 * Build items for one source. Timestamps descend from $startHour, so each
	 * source occupies its own stretch of the calendar and the ordering a test
	 * expects is obvious from the numbers.
	 *
	 * @param string $source
	 * @param int $count
	 * @param int $startHour Larger is newer.
	 * @return IFeedItem[]
	 */
	private function items( string $source, int $count, int $startHour ): array {
		$base = new DateTimeImmutable( '2024-06-01T00:00:00+00:00' );

		$items = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$items[] = $this->item(
				$source,
				"$source $i",
				$base->modify( '-' . ( 200 - $startHour + $i ) . ' hours' )
					->format( 'Y-m-d\TH:i:s\Z' )
			);
		}

		return $items;
	}

	/**
	 * @param IFeedItem[] $items
	 * @param bool $mayHaveMore
	 * @return FeedSourceResult
	 */
	private function result( array $items, bool $mayHaveMore = false ): FeedSourceResult {
		return new FeedSourceResult( $items, $mayHaveMore );
	}

	/**
	 * @param IFeedItem[] $items
	 * @return array<string,int>
	 */
	private function countBySource( array $items ): array {
		$counts = [];
		foreach ( $items as $item ) {
			$source = $item->toArray()['source'];
			$counts[$source] = ( $counts[$source] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * @param IFeedItem[] $items
	 * @return string[]
	 */
	private function keys( array $items ): array {
		return array_map( static fn ( IFeedItem $item ) => $item->getDedupKey(), $items );
	}

	public function testSplitsTheLimitEvenlyWhenEverySourceHasEnough() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
			'b' => $this->result( $this->items( 'b', 10, 20 ) ),
			'c' => $this->result( $this->items( 'c', 10, 20 ) ),
		], 9 );

		$this->assertCount( 9, $merged->items );
		$this->assertSame(
			[ 'a' => 3, 'b' => 3, 'c' => 3 ],
			$this->countBySource( $merged->items )
		);
	}

	public function testGivesTheLeftoverSlotsToTheEarlierSources() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
			'b' => $this->result( $this->items( 'b', 10, 20 ) ),
			'c' => $this->result( $this->items( 'c', 10, 20 ) ),
		], 10 );

		$this->assertSame(
			[ 'a' => 4, 'b' => 3, 'c' => 3 ],
			$this->countBySource( $merged->items )
		);
	}

	public function testIgnoresHowManyItemsASourceHasWhenDividingTheSlots() {
		$merged = ( new FeedMerger() )->merge( [
			'busy' => $this->result( $this->items( 'busy', 100, 28 ) ),
			'quiet' => $this->result( $this->items( 'quiet', 5, 10 ) ),
		], 6 );

		$this->assertSame(
			[ 'busy' => 3, 'quiet' => 3 ],
			$this->countBySource( $merged->items )
		);
	}

	public function testRedistributesTheShareOfAnEmptySource() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
			'empty' => $this->result( [] ),
			'c' => $this->result( $this->items( 'c', 10, 20 ) ),
		], 9 );

		$this->assertCount( 9, $merged->items );
		$this->assertSame(
			[ 'a' => 5, 'c' => 4 ],
			$this->countBySource( $merged->items )
		);
	}

	public function testTakesUpTheShareOfASourceThatRunsOutPartway() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
			'short' => $this->result( $this->items( 'short', 1, 20 ) ),
		], 6 );

		$this->assertSame(
			[ 'a' => 5, 'short' => 1 ],
			$this->countBySource( $merged->items )
		);
	}

	public function testReturnsEverythingWhenTheSourcesCannotFillTheLimit() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 2, 20 ) ),
			'b' => $this->result( $this->items( 'b', 1, 10 ) ),
		], 50 );

		$this->assertCount( 3, $merged->items );
	}

	public function testTakesTheNewestItemsFromWithinEachSource() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
		], 2 );

		$this->assertSame( [ 'a 0', 'a 1' ], $this->keys( $merged->items ) );
	}

	public function testReturnsTheSelectionNewestFirstRegardlessOfSourceOrder() {
		$merged = ( new FeedMerger() )->merge( [
			'older' => $this->result( $this->items( 'older', 3, 10 ) ),
			'newer' => $this->result( $this->items( 'newer', 3, 28 ) ),
		], 6 );

		$timestamps = array_map(
			static fn ( IFeedItem $item ) => $item->getTimestamp(),
			$merged->items
		);
		$sorted = $timestamps;
		rsort( $sorted );

		$this->assertSame( $sorted, $timestamps );
	}

	public function testKeepsOnlyTheFirstCopyOfAKeySeenInMoreThanOneSource() {
		$stamp = '2024-06-01T00:00:00Z';

		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( [ $this->item( 'a', 'Shared', $stamp ) ] ),
			'b' => $this->result( [ $this->item( 'b', 'Shared', $stamp ) ] ),
		], 9 );

		$this->assertCount( 1, $merged->items );
		$this->assertSame( 'a', $merged->items[0]->toArray()['source'] );
	}

	public function testLetsASourceDrawAReplacementWhenItsPickWasADuplicate() {
		// The losing source keeps its share: it moves past the duplicate and
		// contributes its next item instead of going without.
		$stamp = '2024-06-01T00:00:00Z';

		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( [ $this->item( 'a', 'Shared', $stamp ) ] ),
			'b' => $this->result( [
				$this->item( 'b', 'Shared', $stamp ),
				$this->item( 'b', 'B Only', '2024-05-31T00:00:00Z' ),
			] ),
		], 2 );

		$this->assertSame( [ 'Shared', 'B Only' ], $this->keys( $merged->items ) );
	}

	public function testReturnsNothingForAZeroLimit() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
		], 0 );

		$this->assertSame( [], $merged->items );
	}

	public function testReturnsNothingWhenThereAreNoSources() {
		$merged = ( new FeedMerger() )->merge( [], 10 );

		$this->assertSame( [], $merged->items );
		$this->assertSame( [], $merged->cursors );
		$this->assertSame( [], $merged->hasMore );
	}

	public function testDoesNotMutateTheArraysItIsGiven() {
		$items = $this->items( 'a', 3, 10 );
		$before = $this->keys( $items );

		( new FeedMerger() )->merge( [ 'a' => $this->result( $items ) ], 1 );

		$this->assertSame( $before, $this->keys( $items ) );
	}

	public function testANullDedupKeyIsNeverMergedAway() {
		// Two threads on one page are both worth showing, so an item that says
		// it has no shared subject must never be dropped as a duplicate.
		$stamp = '2024-06-01T00:00:00Z';

		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( [
				$this->item( 'a', null, $stamp ),
				$this->item( 'a', null, $stamp ),
			] ),
		], 10 );

		$this->assertCount( 2, $merged->items );
	}

	public function testReportsTheCursorOfTheLastItemTakenFromEachSource() {
		// Not the last item the source offered — the last one the merge used.
		// Resuming from anything else would skip or repeat items.
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
			'b' => $this->result( $this->items( 'b', 10, 20 ) ),
		], 4 );

		$takenFromA = array_values( array_filter(
			$merged->items,
			static fn ( IFeedItem $i ) => $i->toArray()['source'] === 'a'
		) );
		$lastFromA = $takenFromA[ array_key_last( $takenFromA ) ];

		$this->assertSame( $lastFromA->getCursor(), $merged->cursors['a'] );
	}

	public function testTheCursorAdvancesPastAnItemAnotherSourceContributed() {
		// The bug this guards: if everything a source offered is deduped away,
		// reporting no cursor leaves it resuming from the same place. The next
		// page starts with an empty seenKeys, so nothing stops the duplicate the
		// second time round and the same thing shows up twice.
		$stamp = '2026-06-01T00:00:00Z';
		$shared = $this->item( 'b', 'Shared', $stamp );

		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( [
				$this->item( 'a', 'Shared', $stamp ),
				$this->item( 'a', 'A Only', '2026-05-31T00:00:00Z' ),
			] ),
			// Offers only what 'a' already contributed, and says it has more
			// behind it, so it stays in the token for the next page.
			'b' => $this->result( [ $shared ], true ),
		], 2 );

		$this->assertSame(
			$shared->getCursor(),
			$merged->cursors['b'],
			'the skipped duplicate should still move the cursor on'
		);
	}

	public function testAFullyDedupedQueueAdvancesPastItsLastItem() {
		// Everything 'c' offers is already in the page, so it contributes
		// nothing and the skip loop runs to the end of its queue in one go. The
		// cursor has to land on the last item it walked past, not the first:
		// stopping at the first would re-offer the rest on the next page.
		$firstSkipped = $this->item( 'c', 'P', '2026-06-01T00:00:00Z' );
		$lastSkipped = $this->item( 'c', 'Q', '2026-05-31T00:00:00Z' );

		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( [ $this->item( 'a', 'P', '2026-06-01T00:00:00Z' ) ] ),
			'b' => $this->result( [ $this->item( 'b', 'Q', '2026-05-31T00:00:00Z' ) ] ),
			// Both of these duplicate what 'a' and 'b' just contributed, and it
			// reports more behind them, so it stays in the token.
			'c' => $this->result( [ $firstSkipped, $lastSkipped ], true ),
		], 3 );

		$this->assertSame( [ 'P', 'Q' ], $this->keys( $merged->items ), 'c contributed nothing' );
		$this->assertNotSame(
			$firstSkipped->getCursor(),
			$merged->cursors['c'],
			'stopping at the first skipped item would re-offer the second next page'
		);
		$this->assertSame(
			$lastSkipped->getCursor(),
			$merged->cursors['c'],
			'the cursor should be past every item the merge walked over'
		);
		$this->assertTrue( $merged->hasMore['c'], 'c stays live, so its cursor matters' );
	}

	public function testTheCursorTracksWhatWasConsumedNotWhatWasSelected() {
		// A source can take an item and then, on a later round, skip one. The
		// cursor has to end up past the skipped item, not on the selected one.
		$taken = $this->item( 'b', 'B Only', '2026-06-01T00:00:00Z' );
		$skipped = $this->item( 'b', 'Shared', '2026-05-30T00:00:00Z' );

		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( [
				$this->item( 'a', 'A One', '2026-06-02T00:00:00Z' ),
				$this->item( 'a', 'Shared', '2026-05-31T00:00:00Z' ),
			] ),
			'b' => $this->result( [ $taken, $skipped ], true ),
		], 4 );

		$this->assertSame( [ 'A One', 'B Only', 'Shared' ], $this->keys( $merged->items ) );
		$this->assertSame(
			$skipped->getCursor(),
			$merged->cursors['b'],
			'the cursor should be past the duplicate b skipped, not on the item it took'
		);
	}

	public function testReportsNoCursorForASourceThatContributedNothing() {
		// The endpoint keeps whatever cursor it already had for that source.
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
			'empty' => $this->result( [] ),
		], 3 );

		$this->assertNull( $merged->cursors['empty'] );
		$this->assertNotNull( $merged->cursors['a'] );
	}

	public function testReportsMoreWhenItemsWereLeftUnconsumed() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 10, 20 ) ),
		], 2 );

		$this->assertTrue( $merged->hasMore['a'] );
	}

	public function testReportsMoreWhenTheSourceSaysSoEvenIfAllWereUsed() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 2, 20 ), true ),
		], 10 );

		$this->assertCount( 2, $merged->items );
		$this->assertTrue( $merged->hasMore['a'] );
	}

	public function testReportsExhaustedWhenEverythingWasUsedAndTheSourceAgrees() {
		$merged = ( new FeedMerger() )->merge( [
			'a' => $this->result( $this->items( 'a', 2, 20 ), false ),
		], 10 );

		$this->assertFalse( $merged->hasMore['a'] );
	}
}
