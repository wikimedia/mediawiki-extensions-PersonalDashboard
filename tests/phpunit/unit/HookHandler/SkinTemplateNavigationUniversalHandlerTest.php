<?php

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\HookHandler;

use MediaWiki\Extension\PersonalDashboard\HookHandler\SkinTemplateNavigationUniversalHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\PersonalDashboard\HookHandler\SkinTemplateNavigationUniversalHandler
 */
class SkinTemplateNavigationUniversalHandlerTest extends MediaWikiUnitTestCase {
	private User $userMock;
	private SkinTemplate $skinTemplateMock;
	private SkinTemplateNavigationUniversalHandler $handler;

	private string $text = 'personal-dashboard-link-title';
	private string $href = 'https://example.com/test';

	protected function setUp(): void {
		parent::setUp();

		$outputPageMock = $this->createMock( OutputPage::class );
		$this->userMock = $this->createMock( User::class );

		$this->skinTemplateMock = $this->createMock( SkinTemplate::class );
		$this->skinTemplateMock->method( 'getOutput' )->willReturn( $outputPageMock );
		$this->skinTemplateMock->method( 'getUser' )->willReturn( $this->userMock );
		$this->skinTemplateMock->method( 'msg' )->willReturnArgument( 0 );

		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'getFullURL' )->willReturn( $this->href );

		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getTitleForAlias' )->willReturn( $titleMock );

		$this->handler = new SkinTemplateNavigationUniversalHandler( $specialPageFactoryMock );
	}

	/**
	 * @covers ::onSkinTemplateNavigation__Universal
	 */
	public function testLinkExistsIfNamedUser() {
		$links = [ 'user-menu' => [] ];

		$this->userMock->method( 'isNamed' )->willReturn( true );
		$this->handler->onSkinTemplateNavigation__Universal( $this->skinTemplateMock, $links );

		$link = $links['user-menu']['personaldashboard'];
		$this->assertEquals( $this->text, $link['text'] );
		$this->assertEquals( $this->href, $link['href'] );
	}

	/**
	 * @covers ::onSkinTemplateNavigation__Universal
	 */
	public function testLinkNotExistsIfNotNamedUser() {
		$links = [ 'user-menu' => [] ];

		$this->userMock->method( 'isNamed' )->willReturn( false );
		$this->handler->onSkinTemplateNavigation__Universal( $this->skinTemplateMock, $links );

		$this->assertArrayNotHasKey( 'personaldashboard', $links['user-menu'] );
	}
}
