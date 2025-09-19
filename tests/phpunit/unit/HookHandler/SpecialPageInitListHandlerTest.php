<?php

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\HookHandler;

use MediaWiki\Extension\PersonalDashboard\HookHandler\SpecialPageInitListHandler;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\PersonalDashboard\HookHandler\SpecialPageInitListHandler
 */
class SpecialPageInitListHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::onSpecialPage_initList
	 */
	public function testOnSpecialPage_initList() {
		$list = [];
		$handler = new SpecialPageInitListHandler();
		$handler->onSpecialPage_initList( $list );
		$this->assertArrayHasKey( 'PersonalDashboard', $list );
	}
}
