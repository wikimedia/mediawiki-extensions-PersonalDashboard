<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use InvalidArgumentException;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedContinuation;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\FeedContinuation
 */
class FeedContinuationTest extends MediaWikiUnitTestCase {

	private const array SOURCES = [ 'watchlist', 'recentchanges' ];

	/** Must track FeedContinuation::VERSION, which is private. */
	private const int VERSION = 2;

	private function encode( array $cursors, array $sources = self::SOURCES ): string {
		return ( new FeedContinuation( $cursors ) )->encode( $sources );
	}

	public function testRoundTrips(): void {
		$cursors = [ 'watchlist' => '20260310120000|4711', 'recentchanges' => null ];

		$decoded = FeedContinuation::decode( $this->encode( $cursors ), self::SOURCES );

		$this->assertSame( $cursors, $decoded->cursors );
	}

	public function testTheTokenIsUrlSafe(): void {
		// It rides in a query string, so the three characters standard base64
		// uses that need escaping there must not appear.
		$token = $this->encode( [ 'watchlist' => str_repeat( 'ÿ~?', 20 ) ] );

		$this->assertDoesNotMatchRegularExpression( '/[+\/=]/', $token );
	}

	public function testTheSourceOrderDoesNotChangeTheToken(): void {
		// The fingerprint identifies a set, so asking for the same two sources
		// the other way round must still accept the token.
		$token = $this->encode( [ 'watchlist' => 'x' ], [ 'watchlist', 'recentchanges' ] );

		$decoded = FeedContinuation::decode( $token, [ 'recentchanges', 'watchlist' ] );

		$this->assertSame( [ 'watchlist' => 'x' ], $decoded->cursors );
	}

	public function testRejectsATokenMadeForOtherSources(): void {
		// Its cursors would mean something else against a different set, and
		// seeding a source from another source's cursor silently skips items.
		$token = $this->encode( [ 'watchlist' => 'x' ], [ 'watchlist', 'recentchanges' ] );

		$this->expectException( InvalidArgumentException::class );
		FeedContinuation::decode( $token, [ 'watchlist', 'recentlyedited' ] );
	}

	public function testCarriesWhatThePageServed(): void {
		$served = [ 'aabbccdd', '00112233' ];

		$token = ( new FeedContinuation( [ 'watchlist' => 'x' ], $served ) )
			->encode( self::SOURCES );

		$this->assertSame( $served, FeedContinuation::decode( $token, self::SOURCES )->served );
	}

	public function testAFullPageOfServedKeysStillFits(): void {
		// 50 is the endpoint's highest limit, so this is the largest token it can
		// mint. Anything over MAX_TOKEN_BYTES is refused on the way back in, which
		// would strand a client on page one.
		$served = [];
		for ( $i = 0; $i < 50; $i++ ) {
			$served[] = substr( hash( 'sha256', "key $i" ), 0, 8 );
		}

		$token = ( new FeedContinuation(
			[ 'watchlist' => '20260310120000|4711', 'recentchanges' => '20260310115959|4710' ],
			$served
		) )->encode( self::SOURCES );

		$this->assertLessThan( 2048, strlen( $token ) );
		$this->assertCount( 50, FeedContinuation::decode( $token, self::SOURCES )->served );
	}

	/**
	 * @param int $count
	 * @param string $salt
	 * @return string[] Hashes shaped the way FeedMerger writes them
	 */
	private function hashes( int $count, string $salt ): array {
		$out = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = substr( hash( 'sha256', "$salt $i" ), 0, 8 );
		}

		return $out;
	}

	public function testEvictsTheOldestServedKeysToFit() {
		$served = $this->hashes( 2000, 'key' );

		$token = ( new FeedContinuation( [ 'watchlist' => 'x' ], $served ) )
			->encode( self::SOURCES );
		$kept = FeedContinuation::decode( $token, self::SOURCES )->served;

		$this->assertLessThanOrEqual( 2048, strlen( $token ), 'a token we mint must decode' );
		$this->assertLessThan( 2000, count( $kept ), 'that many cannot have fitted' );
		// The newest are the ones worth keeping: a source is likeliest to still
		// be holding a copy of something served recently.
		$this->assertSame( array_slice( $served, -count( $kept ) ), $kept );
	}

	public function testAWholePageOfKeysOutlivesTheEviction() {
		// 50 is the endpoint's highest limit, so this is the most keys one page
		// can add. However long the history in front of them, they are never the
		// ones dropped — losing them would let the next page repeat this one.
		$page = $this->hashes( 50, 'page' );

		$token = ( new FeedContinuation(
			[ 'watchlist' => '20260310120000|4711', 'recentchanges' => '20260310115959|4710' ],
			array_merge( $this->hashes( 2000, 'old' ), $page )
		) )->encode( self::SOURCES );

		$kept = FeedContinuation::decode( $token, self::SOURCES )->served;

		$this->assertSame( $page, array_slice( $kept, -50 ) );
	}

	/**
	 * @dataProvider provideUnusableTokens
	 */
	public function testRejectsAnUnusableToken( string $token, string $why ): void {
		try {
			FeedContinuation::decode( $token, self::SOURCES );
		} catch ( InvalidArgumentException $e ) {
			// FeedHandler logs the reason, so an empty one would leave a rejected
			// token with nothing to explain it.
			$this->assertNotSame( '', $e->getMessage(), 'the reason must say something' );

			return;
		}

		$this->fail( "should have been rejected: $why" );
	}

	public static function provideUnusableTokens(): iterable {
		$b64 = static fn ( $payload ) => rtrim(
			strtr( base64_encode( json_encode( $payload ) ), '+/', '-_' ),
			'='
		);
		$fingerprint = static function ( array $sources ) {
			sort( $sources );
			return substr( hash( 'sha256', implode( '|', $sources ) ), 0, 16 );
		};
		$good = $fingerprint( self::SOURCES );

		yield 'empty' => [ '', 'nothing to read' ];
		yield 'not base64url' => [ '!!!!not base64!!!!', 'undecodable' ];
		yield 'too long' => [ str_repeat( 'a', 2049 ), 'oversized' ];
		yield 'not JSON' => [ rtrim( strtr( base64_encode( 'plain text' ), '+/', '-_' ), '=' ), 'not JSON' ];
		yield 'JSON scalar' => [ $b64( 'a string' ), 'not an object' ];
		yield 'wrong version' => [
			$b64( [ 'v' => 99, 'src' => $good, 'c' => [ 'watchlist' => 'x' ] ] ),
			'a version we do not write',
		];
		yield 'missing version' => [
			$b64( [ 'src' => $good, 'c' => [ 'watchlist' => 'x' ] ] ),
			'no version at all',
		];
		yield 'no live source' => [
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => [] ] ),
			'a token we would never have made',
		];
		yield 'cursors not an object' => [
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => 'nope' ] ),
			'cursors of the wrong shape',
		];
		yield 'unknown source named' => [
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => [ 'nosuchsource' => 'x' ] ] ),
			'a source not in this request',
		];
		yield 'cursor of the wrong type' => [
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => [ 'watchlist' => [ 'nested' ] ] ] ),
			'a cursor that is not a string',
		];
		yield 'the superseded token format' => [
			$b64( [ 'v' => 1, 'src' => $good, 'c' => [ 'watchlist' => 'x' ], 'k' => [ 'aabbccdd' ] ] ),
			'the shape we wrote before the served keys were packed',
		];
		yield 'served keys not a string' => [
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => [ 'watchlist' => 'x' ], 'k' => [ 17 ] ] ),
			'a served list of the wrong type',
		];
		yield 'served keys not base64' => [
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => [ 'watchlist' => 'x' ], 'k' => '!!!!' ] ),
			'a served list that will not decode',
		];
		yield 'served keys truncated mid-hash' => [
			// Three bytes is not a whole number of four-byte hashes, so the
			// split would invent a key out of the remainder.
			$b64( [ 'v' => self::VERSION, 'src' => $good, 'c' => [ 'watchlist' => 'x' ],
				'k' => base64_encode( 'abc' ) ] ),
			'a served list cut off part way through a key',
		];
	}
}
