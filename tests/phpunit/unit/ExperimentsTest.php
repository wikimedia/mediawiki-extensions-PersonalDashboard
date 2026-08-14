<?php
namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit;

use MediaWiki\Extension\PersonalDashboard\Experiments;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Experiments
 */
class ExperimentsTest extends MediaWikiUnitTestCase {

	public function testAllPinsTheRegisteredExperiment() {
		$this->assertSame(
			[ 'T426615' => [ 'treatment' => 'T426615' ] ],
			Experiments::all()
		);
	}

	public function testAllKeysAreNonEmptyStrings() {
		foreach ( array_keys( Experiments::all() ) as $experimentName ) {
			$this->assertIsString( $experimentName );
			$this->assertNotSame( '', $experimentName );
		}
	}

	public function testAllValuesAreVariantMaps() {
		foreach ( Experiments::all() as $variantMap ) {
			$this->assertIsArray( $variantMap );
			foreach ( $variantMap as $variant => $moduleGroup ) {
				$this->assertIsString( $variant );
				$this->assertIsString( $moduleGroup );
				$this->assertNotSame( '', $moduleGroup );
			}
		}
	}

	public function testNoVariantMapsToTheBaselineGroup() {
		// A variant absent from the map falls through to the baseline; naming
		// 'default' explicitly would measure the wrong contrast once a
		// baseline other than 'default' exists for some eligibility slice.
		foreach ( Experiments::all() as $experimentName => $variantMap ) {
			foreach ( $variantMap as $variant => $moduleGroup ) {
				$this->assertNotSame(
					'default',
					$moduleGroup,
					"$experimentName's '$variant' variant must not map to 'default'"
				);
			}
		}
	}

	public function testTagOnlyEntryIsLegal() {
		$manifest = Experiments::all() + [ 'tag-only-example' => [] ];
		$this->assertSame( [], $manifest[ 'tag-only-example' ] );
	}
}
