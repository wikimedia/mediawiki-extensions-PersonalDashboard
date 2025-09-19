<?php

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\HookHandler;

use MediaWiki\Extension\PersonalDashboard\HookHandler\SkinTemplateNavigationUniversalHandler;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\PersonalDashboard\HookHandler\SkinTemplateNavigationUniversalHandler
 */
class SkinTemplateNavigationUniversalHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::onSkinTemplateNavigation__Universal
	 */
	public function testOnSkinTemplateNavigation__Universal() {
		$skinTemplateMock = $this->createNoOpMock( SkinTemplate::class );
		$links = [];

		$text = 'TestText';
		$href = 'http://example.com/test';
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'getText' )->willReturn( $text );
		$titleMock->method( 'getFullURL' )->willReturn( $href );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->expects( $this->once() )->method( 'getTitleForAlias' )->willReturn( $titleMock );
		$handler = new SkinTemplateNavigationUniversalHandler( $specialPageFactoryMock );
		$handler->onSkinTemplateNavigation__Universal( $skinTemplateMock, $links );

		$this->assertArrayHasKey( 'user-menu', $links );
		$testNavLink = $links['user-menu'][0];
		$this->assertArrayHasKey( 'text', $testNavLink );
		$this->assertArrayHasKey( 'href', $testNavLink );
		$this->assertArrayHasKey( 'icon', $testNavLink );
		$this->assertEquals( $text, $testNavLink['text'] );
		$this->assertEquals( $href, $testNavLink['href'] );
	}
}
