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
		$this->configMock->set( 'PersonalDashboardUserMenu', true );
		$this->configMock->set( 'PersonalDashboardMinimumEdits', 0 );
		$this->configMock->set( 'PersonalDashboardMaximumEdits', 0 );
		$this->configMock->set( 'PersonalDashboardBlueDot', true );

		$outputMock = $this->createMock( OutputPage::class );

		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'getFullURL' )->willReturnReference( $this->href );
		$titleMock->method( 'isSpecial' )->willReturnReference( $this->isSelf );

		$this->userMock = $this->createMock( User::class );
		$this->userMock->method( 'isNamed' )->willReturnReference( $this->isNamed );

		$this->skinTemplateMock = $this->createMock( SkinTemplate::class );
		$this->skinTemplateMock->method( 'getConfig' )->willReturn( $this->configMock );
		$this->skinTemplateMock->method( 'getOutput' )->willReturn( $outputMock );
		$this->skinTemplateMock->method( 'getTitle' )->willReturn( $titleMock );
		$this->skinTemplateMock->method( 'getUser' )->willReturn( $this->userMock );
		$this->skinTemplateMock->method( 'msg' )->willReturnArgument( 0 );

		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getTitleForAlias' )->willReturn( $titleMock );

		$userOptionsManagerMock = $this->createMock( UserOptionsManager::class );
		$userOptionsManagerMock
			->method( 'getBoolOption' )
			->willReturnReference( $this->hasVisited );

		$userEditTrackerMock = $this->createMock( UserEditTracker::class );
		$userEditTrackerMock
			->method( 'getUserEditCount' )
			->willReturnReference( $this->editCount );

		$this->handler = new SkinTemplateNavigationUniversalHandler(
			$specialPageFactoryMock,
			$userOptionsManagerMock,
			$userEditTrackerMock
		);
	}

	/** @covers ::onSkinTemplateNavigation__Universal */
	public function testLinkExists() {
		$links = [ 'user-menu' => [] ];
		$this->handler->onSkinTemplateNavigation__Universal( $this->skinTemplateMock, $links );

		$link = $links['user-menu']['personaldashboard'];
		$this->assertEquals( $this->text, $link['text'] );
		$this->assertEquals( $this->href, $link['href'] );
	}

	/** @covers ::isMenuLinkVisible */
	public function testHideLinkIfNotNamedUser() {
		$this->isNamed = false;

		$this->assertFalse( $this->handler->isMenuLinkVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->isNamed = true;
	}

	/** @covers ::isMenuLinkVisible */
	public function testHideLinkIfDisabled() {
		$this->configMock->set( 'PersonalDashboardUserMenu', false );

		$this->assertFalse( $this->handler->isMenuLinkVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->configMock->set( 'PersonalDashboardUserMenu', true );
	}

	/** @covers ::isMenuLinkVisible */
	public function testShowLinkIfPreviouslyEligible() {
		$this->hasVisited = true;

		$this->assertTrue( $this->handler->isMenuLinkVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->hasVisited = false;
	}

	/** @covers ::isMenuLinkVisible */
	public function testLinkThreshold() {
		$this->configMock->set( 'PersonalDashboardMinimumEdits', 100 );
		$this->configMock->set( 'PersonalDashboardMaximumEdits', 500 );

		$this->editCount = 99;
		$this->assertFalse( $this->handler->isMenuLinkVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->editCount = 501;
		$this->assertFalse( $this->handler->isMenuLinkVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->editCount = 100;
		$this->assertTrue( $this->handler->isMenuLinkVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->configMock->set( 'PersonalDashboardMinimumEdits', 0 );
		$this->configMock->set( 'PersonalDashboardMaximumEdits', 0 );
	}

	/** @covers ::isBlueDotVisible */
	public function testShowBlueDot() {
		$this->assertTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );
	}

	/** @covers ::isBlueDotVisible */
	public function testHideBlueDotIfDisabled() {
		$this->configMock->set( 'PersonalDashboardBlueDot', false );

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->configMock->set( 'PersonalDashboardBlueDot', true );
	}

	/** @covers ::isBlueDotVisible */
	public function testHideBlueDotIfSelf() {
		$this->isSelf = true;

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->isSelf = false;
	}

	/** @covers ::isBlueDotVisible */
	public function testHideBlueDotIfVisited() {
		$this->hasVisited = true;

		$this->assertNotTrue( $this->handler->isBlueDotVisible(
			$this->skinTemplateMock,
			$this->userMock
		) );

		$this->hasVisited = false;
	}
}
