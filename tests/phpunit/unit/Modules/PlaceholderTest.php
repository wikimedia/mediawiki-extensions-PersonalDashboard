<?php
namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\Extension\PersonalDashboard\Modules\Placeholder;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\Placeholder
 */
class PlaceholderTest extends MediaWikiUnitTestCase {

	public function testRenderEmitsWrapperAndEmptySlot() {
		$module = new Placeholder();
		$module->setName( 'foo' );

		$html = $module->render();

		$this->assertStringContainsString( 'personal-dashboard-module', $html );
		$this->assertStringContainsString( 'personal-dashboard-module-foo', $html );
		$this->assertStringContainsString( 'data-module-name="foo"', $html );
		$this->assertStringNotContainsString( 'data-platform', $html );
		$this->assertStringContainsString( 'personal-dashboard-module-body', $html );
		// The empty mount slot the client teleports into: id and class present, no
		// inner content, so nothing competes with the island the client mounts here.
		$this->assertStringContainsString(
			'<div id="pd-slot-foo" class="personal-dashboard-module-slot"></div>',
			$html
		);
	}

	public function testGetJsDataReportsServerRendered() {
		$module = new Placeholder();

		// serverRendered => true is the load-bearing key: it tells the client to
		// leave the placeholder frame alone rather than mount an island over it.
		$this->assertEqualsCanonicalizing(
			[
				'enabled' => true,
				'expandable' => false,
				'serverRendered' => true,
			],
			$module->getJsData()
		);
	}

	public function testSupportsIsTrue() {
		$module = new Placeholder();

		$this->assertTrue( $module->supports() );
	}
}
