<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Message\Message;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\BaseModule
 */
class BaseModuleTest extends MediaWikiUnitTestCase {

	// A concrete BaseModule fixture with a settable serverRendered() flag, a
	// distinctive body sentinel, and public pass-throughs for the protected render
	// helpers under test. Anonymous so the file keeps to one named class.
	private function newModule( bool $shouldWrapModuleWithLink = false ) {
		$message = $this->createMock( Message::class );
		$message->method( 'text' )->willReturn( '' );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'msg' )->willReturn( $message );

		return new class(
			$context,
			$shouldWrapModuleWithLink
		) extends BaseModule {

			public bool $serverRenderedFlag = false;

			protected function getHeaderText(): string {
				return '';
			}

			protected function serverRendered(): bool {
				return $this->serverRenderedFlag;
			}

			protected function getBody(): string {
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

		$this->assertStringStartsWith(
			'<div id="pd-slot-impact" class="personal-dashboard-module-slot"></div>',
			$module->callGetSlot()
		);
	}

	public function testBuildModuleWrapperEmitsPlainDivWithoutAnchor() {
		$module = $this->newModule( true );
		$module->setName( 'impact' );
		$module->setPageURL( '/wiki/Special:PersonalDashboard' );

		$html = $module->callBuildModuleWrapper( '<span>x</span>' );

		// The whole-card anchor is gone: the wrapper is a plain div carrying the base
		// and per-module classes, no anchor and no per-platform class.
		$this->assertStringContainsString( 'id="impact"', $html );
		$this->assertStringContainsString( 'personal-dashboard-module', $html );
		$this->assertStringContainsString( 'personal-dashboard-module-impact', $html );
		$this->assertStringContainsString( 'data-module-name="impact"', $html );
		$this->assertStringNotContainsString( 'personal-dashboard-module-anchor', $html );
		$this->assertStringNotContainsString( 'data-platform', $html );
	}
}
