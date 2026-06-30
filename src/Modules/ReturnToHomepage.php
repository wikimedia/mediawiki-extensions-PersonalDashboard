<?php

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
	public function render( $mode ) {
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
	public function setName( string $name ) {
	}

	/** @inheritDoc */
	public function getJsData( $mode ) {
		return [ 'html' => $this->render( $mode ) ];
	}

	/** @inheritDoc */
	public function getJsConfigVars() {
		return [];
	}

	/** @inheritDoc */
	public function supports( $mode ) {
		return ExtensionRegistry::getInstance()->isLoaded( 'GrowthExperiments' );
	}

	/** @inheritDoc */
	public function setPageURL( string $url ) {
	}
}
