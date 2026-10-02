<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration;

use MediaWiki\Extension\PersonalDashboard\Experiments;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

/**
 * The manifest's variant targets and extension.json's registered module groups
 * are two hand-edited lists that have to agree, and a mismatch stays silent at
 * runtime: the group never resolves, nothing is logged, and only the unroutable
 * counter moves. ExperimentsTest pins the manifest's own content; this checks it
 * against the registry the special page actually looks the group up in.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Experiments
 */
class ExperimentsRegistryTest extends MediaWikiIntegrationTestCase {

	public function testEveryVariantTargetIsARegisteredModuleGroup(): void {
		$registry = ExtensionRegistry::getInstance()->getAttribute( 'PersonalDashboardModuleGroups' );

		foreach ( Experiments::all() as $experimentName => $variantMap ) {
			foreach ( $variantMap as $variant => $moduleGroup ) {
				$this->assertArrayHasKey(
					$moduleGroup,
					$registry,
					"$experimentName's '$variant' variant targets the module group "
						. "'$moduleGroup', which extension.json does not register"
				);
			}
		}
	}
}
