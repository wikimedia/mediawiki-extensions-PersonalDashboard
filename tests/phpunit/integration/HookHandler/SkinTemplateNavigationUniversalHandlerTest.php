<?php

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\PersonalDashboard\HookHandler\SkinTemplateNavigationUniversalHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWikiIntegrationTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\PersonalDashboard\HookHandler\SkinTemplateNavigationUniversalHandler
 */
class SkinTemplateNavigationUniversalHandlerTest extends MediaWikiIntegrationTestCase {
	private HashConfig $configMock;
	private OutputPage $outputMock;
	private Title $titleMock;
	private User $userMock;
	private SkinTemplate $skinTemplateMock;
	private SkinTemplateNavigationUniversalHandler $handler;

	private string $text = 'personal-dashboard-link-title';
	private string $href = 'https://example.com/test';
	private bool $isNamed = true;
	private bool $isSelf = false;
	private bool $hasVisited = false;
	private int $editCount = 100;

	protected function setUp(): void {
		parent::setUp();

		$this->configMock = new HashConfig();
		$this->configMock->set( 'PersonalDashboardBlueDot', true );
		$this->configMock->set( 'PersonalDashboardBlueDotMinimumEdits', 100 );

		$this->outputMock = $this->createMock( OutputPage::class );

		$this->titleMock = $this->createMock( Title::class );
		$this->titleMock->method( 'getFullURL' )->willReturn( $this->href );
		$this->titleMock->method( 'isSpecial' )->willReturnReference( $this->isSelf );

		$this->userMock = $this->createMock( User::class );
		$this->userMock->method( 'isNamed' )->willReturnReference( $this->isNamed );

		$this->skinTemplateMock = $this->createMock( SkinTemplate::class );
		$this->skinTemplateMock->method( 'getConfig' )->willReturn( $this->configMock );
		$this->skinTemplateMock->method( 'getOutput' )->willReturn( $this->outputMock );
		$this->skinTemplateMock->method( 'getTitle' )->willReturn( $this->titleMock );
		$this->skinTemplateMock->method( 'getUser' )->willReturn( $this->userMock );
		$this->skinTemplateMock->method( 'msg' )->willReturnArgument( 0 );

		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getTitleForAlias' )->willReturn( $this->titleMock );

		$userOptionsManagerMock = $this->createMock( UserOptionsManager::class );
		$userOptionsManagerMock->method( 'getBoolOption' )->willReturnReference( $this->hasVisited );

		$userEditTrackerMock = $this->createMock( UserEditTracker::class );
		$userEditTrackerMock->method( 'getUserEditCount' )->willReturnReference( $this->editCount );

		$this->handler = new SkinTemplateNavigationUniversalHandler(
			$specialPageFactoryMock,
			$userOptionsManagerMock,
			$userEditTrackerMock
		);
	}

	/**
	 * @covers ::onSkinTemplateNavigation__Universal
	 */
	public function testLinkExistsIfNamedUser() {
		$links = [ 'user-menu' => [] ];
		$this->handler->onSkinTemplateNavigation__Universal( $this->skinTemplateMock, $links );

		$link = $links['user-menu']['personaldashboard'];
		$this->assertEquals( $this->text, $link['text'] );
		$this->assertEquals( $this->href, $link['href'] );
	}

	/**
	 * @covers ::onSkinTemplateNavigation__Universal
	 */
	public function testLinkNotExistsIfNotNamedUser() {
		$this->isNamed = false;

		$links = [ 'user-menu' => [] ];
		$this->handler->onSkinTemplateNavigation__Universal( $this->skinTemplateMock, $links );
		$this->assertArrayNotHasKey( 'personaldashboard', $links['user-menu'] );

		$this->isNamed = true;
	}

	/**
	 * @covers ::isBlueDotVisible
	 */
	public function testShowBlueDotIfEnabled() {
		$this->assertTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );
	}

	/**
	 * @covers ::isBlueDotVisible
	 */
	public function testHideBlueDotIfDisabled() {
		$this->configMock->set( 'PersonalDashboardBlueDot', false );

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->configMock->set( 'PersonalDashboardBlueDot', true );
	}

	/**
	 * @covers ::isBlueDotVisible
	 */
	public function testHideBlueDotIfSelf() {
		$this->isSelf = true;

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->isSelf = false;
	}

	/**
	 * @covers ::isBlueDotVisible
	 */
	public function testHideBlueDotIfVisited() {
		$this->hasVisited = true;

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->hasVisited = false;
	}

	/**
	 * @covers ::isBlueDotVisible
	 */
	public function testHideBlueDotIfBelowMinimumEdits() {
		$this->editCount = 99;

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->editCount = 100;
	}
}
