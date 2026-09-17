<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\PersonalDashboard\Feed\OresRevisionScoreLookup;
use MediaWiki\Extension\PersonalDashboard\Feed\OresScoreFormatter;
use MediaWikiUnitTestCase;
use ORES\Storage\StorageScoreLookup;
use ORES\Storage\ThresholdLookup;

/**
 * The threshold the card flags an edit against. Every branch here ends in
 * "no threshold", which the card reads as "make no check" rather than as a
 * number that flags nothing.
 *
 * Needs ORES types to mock, so it skips where ORES is not installed — which
 * includes CI, since PersonalDashboard does not require it.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\OresRevisionScoreLookup
 */
class OresRevisionScoreLookupTest extends MediaWikiUnitTestCase {

	private const string MODEL = 'revertrisklanguageagnostic';

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( ThresholdLookup::class ) ) {
			$this->markTestSkipped( 'ORES is not installed' );
		}
	}

	private function newLookup( array $config, array $thresholds ): OresRevisionScoreLookup {
		$thresholdLookup = $this->createMock( ThresholdLookup::class );
		$thresholdLookup->method( 'getThresholds' )->willReturn( $thresholds );

		return new OresRevisionScoreLookup(
			$this->createMock( StorageScoreLookup::class ),
			$thresholdLookup,
			new OresScoreFormatter(),
			new HashConfig( $config + [
				'OresUiEnabled' => true,
				'PersonalDashboardReviewChangesMlModel' => self::MODEL,
				'OresModels' => [ self::MODEL => [ 'enabled' => true ] ],
				'OresModelClasses' => [],
			] )
		);
	}

	public function testReportsTheWikisThresholdForTheModelItScoresWith() {
		$lookup = $this->newLookup( [], [ 'revertrisk' => [ 'min' => 0.95, 'max' => 1 ] ] );

		$this->assertSame(
			[ 'model' => self::MODEL, 'class' => 'true', 'min' => 0.95 ],
			$lookup->getHighRiskThreshold()
		);
	}

	public function testReportsTheThresholdAsAFloat() {
		// The client compares a number against it, and JSON should carry one.
		$lookup = $this->newLookup( [], [ 'revertrisk' => [ 'min' => '0.95' ] ] );

		$this->assertIsFloat( $lookup->getHighRiskThreshold()['min'] );
	}

	public function testReportsNothingWhereTheWikiSwitchedTheOresInterfaceOff() {
		$lookup = $this->newLookup(
			[ 'OresUiEnabled' => false ],
			[ 'revertrisk' => [ 'min' => 0.95 ] ]
		);

		$this->assertNull( $lookup->getHighRiskThreshold() );
	}

	public function testReportsNothingWhereTheWikiNamedNoModel() {
		$lookup = $this->newLookup(
			[ 'PersonalDashboardReviewChangesMlModel' => '' ],
			[ 'revertrisk' => [ 'min' => 0.95 ] ]
		);

		$this->assertNull( $lookup->getHighRiskThreshold() );
	}

	public function testReportsNothingWhereTheModelIsNotEnabled() {
		$lookup = $this->newLookup(
			[ 'OresModels' => [ self::MODEL => [ 'enabled' => false ] ] ],
			[ 'revertrisk' => [ 'min' => 0.95 ] ]
		);

		$this->assertNull( $lookup->getHighRiskThreshold() );
	}

	public function testReportsNothingForAModelWithNoHighRiskBand() {
		$lookup = $this->newLookup(
			[
				'PersonalDashboardReviewChangesMlModel' => 'articlequality',
				'OresModels' => [ 'articlequality' => [ 'enabled' => true ] ],
			],
			[ 'revertrisk' => [ 'min' => 0.95 ] ]
		);

		$this->assertNull( $lookup->getHighRiskThreshold() );
	}

	public function testReportsNothingWhereTheWikiConfiguredNoBandForTheFilter() {
		// What getThresholds() returns for a model the wiki left out of
		// $wgOresFiltersThresholds, and after an ORES service failure.
		$lookup = $this->newLookup( [], [] );

		$this->assertNull( $lookup->getHighRiskThreshold() );
	}

	public function testReportsNothingWhereTheBandCarriesNoMinimum() {
		$lookup = $this->newLookup( [], [ 'revertrisk' => [ 'max' => 1 ] ] );

		$this->assertNull( $lookup->getHighRiskThreshold() );
	}
}
