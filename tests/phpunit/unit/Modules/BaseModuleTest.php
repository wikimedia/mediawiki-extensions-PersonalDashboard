<?php
namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\BaseModule
 */
class BaseModuleTest extends MediaWikiUnitTestCase {

	// A concrete BaseModule fixture with a settable serverRendered() flag, a
	// distinctive body sentinel, and public pass-throughs for the protected render
	// helpers under test. Anonymous so the file keeps to one named class.
	private function newModule( bool $shouldWrapModuleWithLink = false ) {
		return new class(
			$this->createMock( IContextSource::class ),
			$shouldWrapModuleWithLink
		) extends BaseModule {

			public bool $serverRenderedFlag = false;

			protected function getHeaderText() {
				return '';
			}

			protected function serverRendered(): bool {
				return $this->serverRenderedFlag;
			}

			protected function getBody() {
				return '<p>SERVER_BODY_SENTINEL</p>';
			}

			public function callGetBodyContent(): string {
				return $this->getBodyContent();
			}

			public function callGetSlot(): string {
				return $this->getSlot();
			}

			public function callBuildModuleWrapper( string ...$sections ): string {
				return $this->buildModuleWrapper( ...$sections );
			}

			public function callSetPlatform( string $platform ): void {
				$this->setPlatform( $platform );
			}
		};
	}

	public function testGetBodyContentReturnsSlotForIslandModule() {
		$module = $this->newModule();
		$module->serverRenderedFlag = false;
		$module->setName( 'impact' );

		$body = $module->callGetBodyContent();

		$this->assertStringContainsString( 'id="pd-slot-impact"', $body );
		$this->assertStringContainsString( 'class="personal-dashboard-module-slot"', $body );
		$this->assertStringNotContainsString( 'SERVER_BODY_SENTINEL', $body );
	}

	public function testGetBodyContentReturnsBodyForServerRenderedModule() {
		$module = $this->newModule();
		$module->serverRenderedFlag = true;
		$module->setName( 'impact' );

		$body = $module->callGetBodyContent();

		$this->assertStringContainsString( 'SERVER_BODY_SENTINEL', $body );
		$this->assertStringNotContainsString( 'pd-slot-', $body );
	}

	public function testGetSlotEmitsEmptyMountDiv() {
		$module = $this->newModule();
		$module->setName( 'impact' );

		$this->assertSame(
			'<div id="pd-slot-impact" class="personal-dashboard-module-slot"></div>',
			$module->callGetSlot()
		);
	}

}
