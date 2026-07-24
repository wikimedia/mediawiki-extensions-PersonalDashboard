<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Html\Html;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * A module to allow users to navigate back to the homepage.
 */
class ReturnToHomepage implements IModule {

	public function __construct() {
	}

	/** @inheritDoc */
	public function render(): string {
		$linkUrl = SpecialPage::getTitleFor( 'Homepage' )
			->getFullURL( [ 'source' => 'specialpersonaldashboard' ] );
		$linkEl = Html::rawElement(
			'a',
			[
				'href' => $linkUrl,
			],
			(string)SpecialPage::getTitleFor( 'Homepage' )
		);
		return Html::rawElement(
			'div',
			[
				'class' => 'returntohomepage'
			],
			$linkEl,
		);
	}

	/** @inheritDoc */
	public function setName( string $name ): void {
	}

	/** @inheritDoc */
	public function getJsData(): array {
		// Server-rendered: render() output stays in the server DOM and the client
		// leaves it alone, so no body travels in the bootstrap.
		return [
			'enabled' => true,
			'serverRendered' => true,
		];
	}

	/** @inheritDoc */
	public function getJsConfigVars(): array {
		return [];
	}

	/** @inheritDoc */
	public function supports(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'GrowthExperiments' );
	}

	/** @inheritDoc */
	public function setPageURL( string $url ): void {
	}
}
