<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\OresScoreFormatter;
use MediaWikiUnitTestCase;
use stdClass;

/**
 * The payload a client reads has to match what the Action API emits for
 * rcprop=oresscores, or a consumer of both gets two different answers for one
 * edit. These cases pin the three parts that are easy to get wrong.
 *
 * Deliberately free of any ORES type, so it runs where ORES is not installed —
 * which includes CI, since PersonalDashboard does not require it.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\OresScoreFormatter
 */
class OresScoreFormatterTest extends MediaWikiUnitTestCase {

	private const string MODEL = 'revertrisklanguageagnostic';
	private const array CLASSES = [ 'false' => 0, 'true' => 1 ];

	private function row( int $revId, int $class, string $probability ): stdClass {
		// Values arrive as strings: oresc_probability is decimal(3,3).
		return (object)[
			'oresc_rev' => (string)$revId,
			'oresc_class' => (string)$class,
			'oresc_probability' => $probability,
		];
	}

	private function format( array $rows ): array {
		return ( new OresScoreFormatter() )->format( $rows, self::MODEL, self::CLASSES );
	}

	public function testBuildsTheShapeTheActionApiEmits() {
		$scores = $this->format( [ $this->row( 4711, 1, '0.870' ) ] );

		$this->assertSame(
			[ 4711 => [ self::MODEL => [ 'true' => 0.87, 'false' => 0.13 ] ] ],
			$scores
		);
	}

	public function testDerivesTheNegativeClassBecauseOnlyThePositiveOneIsStored() {
		$scores = $this->format( [ $this->row( 1, 1, '0.250' ) ] );

		$this->assertSame( 0.75, $scores[1][self::MODEL]['false'] );
	}

	public function testProbabilitiesComeBackAsFloats() {
		// The database hands back a string. A client comparing it against a
		// threshold needs a number, and JSON should carry one too.
		$scores = $this->format( [ $this->row( 1, 1, '0.500' ) ] );

		$this->assertIsFloat( $scores[1][self::MODEL]['true'] );
		$this->assertIsFloat( $scores[1][self::MODEL]['false'] );
	}

	public function testKeysByRevisionIdAsAnInteger() {
		$scores = $this->format( [
			$this->row( 12, 1, '0.100' ),
			$this->row( 34, 1, '0.900' ),
		] );

		$this->assertSame( [ 12, 34 ], array_keys( $scores ) );
	}

	public function testAnUnscoredRevisionIsSimplyAbsent() {
		// Callers read this with `?? []`, so an empty result is the whole
		// contract for "no score".
		$this->assertSame( [], $this->format( [] ) );
	}

	public function testIgnoresAClassTheModelDoesNotDeclare() {
		// A row for a class not in $wgOresModelClasses has no name to go under,
		// and inventing one would put a key in the payload no client expects.
		$scores = $this->format( [ $this->row( 1, 7, '0.500' ) ] );

		$this->assertSame( [], $scores );
	}

	public function testDoesNotInventANegativeClassWhenTheModelHasNone() {
		$scores = ( new OresScoreFormatter() )
			->format( [ $this->row( 1, 1, '0.400' ) ], self::MODEL, [ 'true' => 1 ] );

		$this->assertSame( [ 1 => [ self::MODEL => [ 'true' => 0.4 ] ] ], $scores );
	}
}
