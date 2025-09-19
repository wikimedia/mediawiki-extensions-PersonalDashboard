<?php

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\HookHandler;

use MediaWiki\Extension\PersonalDashboard\HookHandler\BeforePageDisplayHandler;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\PersonalDashboard\HookHandler\BeforePageDisplayHandler
 */
class BeforePageDisplayHandlerTest extends MediaWikiUnitTestCase {

	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @covers ::onBeforePageDisplay
	 */
	public function testOnBeforePageDisplay() {
		// mock handler object
		$handler = new BeforePageDisplayHandler();

		// mock inputs and run handler
		$outputPageMock = $this->createMock( OutputPage::class );
		$outputPageMock->expects( $this->once() )
			->method( 'addModuleStyles' )
			->with( 'ext.personalDashboard.mobileMenu.icons' );
		$skinMock = $this->createNoOpMock( SkinMinerva::class );
		$handler->onBeforePageDisplay( $outputPageMock, $skinMock );
	}
}
