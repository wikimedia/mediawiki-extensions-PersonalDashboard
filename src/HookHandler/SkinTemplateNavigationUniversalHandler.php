<?php
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPageFactory;

class SkinTemplateNavigationUniversalHandler implements
	SkinTemplateNavigation__UniversalHook
{

	private SpecialPageFactory $specialPageFactory;

	public function __construct( SpecialPageFactory $specialPageFactory ) {
		$this->specialPageFactory = $specialPageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		$specialTitle = $this->specialPageFactory->getTitleForAlias( 'PersonalDashboard' );
		$links['user-menu'][] = [
			'text' => $specialTitle->getText(),
			'href' => $specialTitle->getFullURL(),
			'icon' => 'newspaper',
		];
	}
}
