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
	public function render( $mode ): string {
		if ( !$this->supports( $mode ) ) {
			return '';
		}
		return Html::rawElement(
			'div',
			[
				'class' => [
					'personal-dashboard-module',
					'personal-dashboard-module-' . $this->name,
					'personal-dashboard-module-' . $mode,
				],
				'data-module-name' => $this->name,
				'data-mode' => $mode,
			],
			Html::rawElement( 'div',
				[ 'class' => 'personal-dashboard-module-body' ],
				Html::rawElement( 'div', [
					'id' => $this->name . '-vue-root',
					'class' => 'ext-personal-dashboard-app-root',
				] )
			)
		);
	}

	/** @inheritDoc */
	public function getJsData( $mode ): array {
		if ( !$this->supports( $mode ) ) {
			return [];
		}
		return [ 'renderMode' => $mode ];
	}

	/** @inheritDoc */
	public function supports( $mode ): bool {
		return true;
	}

	/** @inheritDoc */
	public function setPageURL( string $url ): void {
	}
}
