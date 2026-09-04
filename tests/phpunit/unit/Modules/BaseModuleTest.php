<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\BaseModule
 */
class BaseModuleTest extends MediaWikiUnitTestCase {

	// A concrete BaseModule fixture with a settable serverRendered() flag, a
	// distinctive body sentinel, and public pass-throughs for the protected render
	// helpers under test. Anonymous so the file keeps to one named class.
	private function newModule( bool $shouldWrapModuleWithLink = false ) {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'msg' )->willReturnCallback( function ( $key ) {
			$message = $this->createMock( Message::class );
			$message->method( 'text' )->willReturn( 'msg-' . $key );
			return $message;
		} );

		$title = $this->createMock( Title::class );
		$title->method( 'getLinkURL' )->willReturn( '/wiki/Special:PersonalDashboard' );
		$context->method( 'getTitle' )->willReturn( $title );

		return new class(
			$context,
			$shouldWrapModuleWithLink
		) extends BaseModule {

			public bool $serverRenderedFlag = false;
			public string $headerTextValue = '';
			public string $subheaderTextValue = '';

			protected function getHeaderText(): string {
				return $this->headerTextValue;
			}

			protected function getSubheaderText(): string {
				return $this->subheaderTextValue;
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

			public function callGetHtml(): string {
				return $this->getHtml();
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

	public function testCardHeaderLinksToFocusedPageAndIsNamedByItsText() {
		$module = $this->newModule( true );
		$module->setName( 'impact' );
		$module->setPageURL( '/wiki/Special:PersonalDashboard' );
		$module->headerTextValue = 'Impact';

		$html = $module->callGetHtml();

		$this->assertStringContainsString(
			'href="/wiki/Special:PersonalDashboard/impact"', $html );
		$this->assertStringContainsString(
			'class="personal-dashboard-module-header-container"', $html );
		$this->assertStringContainsString(
			'personal-dashboard-module-header-forward-icon', $html );
		$this->assertStringContainsString( '>Impact<', $html );
		// The visible text names the link, so an aria-label would only override it.
		$this->assertStringNotContainsString( 'aria-label', $html );
	}

	public function testCardHeaderLinkUsesPageURLNotTheDeepLinkedContextTitle() {
		// The context title carries whatever subpage is already in the current
		// URL (a deep-linked module render); getPageURL() is the dashboard's
		// own, subpage-free base, set once per request regardless of which
		// module is being rendered. getCardHeader() must build its link from
		// the latter, or a deep link produces a garbled nested path.
		$context = $this->createMock( IContextSource::class );
		$title = $this->createMock( Title::class );
		$title->method( 'getLinkURL' )->willReturn( '/wiki/Special:PersonalDashboard/policiesGuidelines' );
		$context->method( 'getTitle' )->willReturn( $title );

		$module = new class( $context, true ) extends BaseModule {
			protected function getHeaderText(): string {
				return 'Impact';
			}

			public function callGetHtml(): string {
				return $this->getHtml();
			}
		};
		$module->setName( 'impact' );
		$module->setPageURL( '/wiki/Special:PersonalDashboard' );

		$html = $module->callGetHtml();

		$this->assertStringContainsString(
			'href="/wiki/Special:PersonalDashboard/impact"', $html );
		$this->assertStringNotContainsString( 'policiesGuidelines', $html );
	}

	public function testHeaderlessCardHeaderKeepsArrowAndCarriesAccessibleName() {
		$module = $this->newModule( true );
		$module->setName( 'banner' );
		$module->headerTextValue = '';

		$html = $module->callGetHtml();

		$this->assertStringContainsString(
			'personal-dashboard-module-header-forward-icon', $html );
		$this->assertStringContainsString(
			'aria-label="msg-personal-dashboard-open-module"', $html );
		$this->assertStringNotContainsString(
			'personal-dashboard-module-header-text', $html );
	}

	public function testCardHeaderIsPlainContainerWhenLinkWrappingIsOff() {
		$module = $this->newModule();
		$module->setName( 'impact' );
		$module->headerTextValue = 'Impact';

		$html = $module->callGetHtml();

		$this->assertStringContainsString( '>Impact<', $html );
		$this->assertStringNotContainsString( '<a', $html );
		$this->assertStringNotContainsString(
			'personal-dashboard-module-header-forward-icon', $html );
	}

	public function testFocusedRenderUsesBackLinkAndDropsSubheader() {
		$module = $this->newModule();
		$module->setName( 'impact' );
		$module->headerTextValue = 'Impact';
		$module->subheaderTextValue = 'SUBHEADER_SENTINEL';
		$module->setBackLink( '<a id="BACK_SENTINEL"></a>' );
		$module->setFocused( true );

		$html = $module->callGetHtml();

		$this->assertStringContainsString( 'BACK_SENTINEL', $html );
		$this->assertStringContainsString( 'personal-dashboard-module--focused', $html );
		$this->assertStringNotContainsString( 'SUBHEADER_SENTINEL', $html );
		$this->assertStringNotContainsString(
			'personal-dashboard-module-header-forward-icon', $html );
	}

	public function testWrapperCarriesDesktopAndMobileStyleClasses() {
		$module = $this->newModule();
		$module->setName( 'impact' );
		$module->setStyles( 'thin', 'none' );

		$html = $module->callBuildModuleWrapper( '<span>x</span>' );

		$this->assertStringContainsString( 'personal-dashboard-module--style-thin', $html );
		$this->assertStringContainsString( 'personal-dashboard-module--style-mobile-none', $html );
	}
}
