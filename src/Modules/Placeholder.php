<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Html\Html;

/**
 * Minimal IModule implementation for Vue-only modules that handle
 * all rendering client-side. Emits only the app root div that
 * init.js expects to mount into.
 */
class Placeholder implements IModule {

	/** @var string Name of the module */
	protected string $name;

	public function __construct() {
	}

	/** @inheritDoc */
	public function setName( string $name ): void {
		$this->name = $name;
	}

	/** @inheritDoc */
	public function render( string $platform ): string {
		return Html::rawElement(
			'div',
			[
				'class' => [
					'personal-dashboard-module',
					'personal-dashboard-module-' . $this->name,
					'personal-dashboard-module-' . $platform,
				],
				'data-module-name' => $this->name,
				'data-platform' => $platform,
			],
			Html::rawElement( 'div',
				[ 'class' => 'personal-dashboard-module-body' ],
				Html::element( 'div', [
					'id' => 'pd-slot-' . $this->name,
					'class' => 'personal-dashboard-module-slot',
				] )
			)
		);
	}

	/** @inheritDoc */
	public function getJsData( string $platform ): array {
		return [
			'enabled' => true,
			'expandable' => false,
			'serverRendered' => false,
		];
	}

	/** @inheritDoc */
	public function getJsConfigVars() {
		return [];
	}

	/** @inheritDoc */
	public function supports( string $platform ): bool {
		return true;
	}

	/** @inheritDoc */
	public function setPageURL( string $url ): void {
	}
}
