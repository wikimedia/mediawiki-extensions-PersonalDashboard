<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Extension\PersonalDashboard\IModule;
use MediaWiki\Html\Html;

/**
 * Minimal IModule implementation substituted for an unregistered module: a frame
 * with an empty body. It reports serverRendered() so the client leaves it be,
 * rather than flattening it into an island that lazy-loads a module that was never
 * registered.
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
	public function render(): string {
		return Html::rawElement(
			'div',
			[
				'class' => [
					'personal-dashboard-module',
					// Mirrors the per-module class from BaseModule::buildModuleWrapper(),
					// built from whatever unregistered module name config requested, so
					// the possible classes cannot be enumerated here.
					'personal-dashboard-module-' . $this->name,
				],
				'data-module-name' => $this->name,
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
	public function getJsData(): array {
		return [
			'enabled' => true,
			'expandable' => false,
			'serverRendered' => true,
		];
	}

	/** @inheritDoc */
	public function getJsConfigVars(): array {
		return [];
	}

	/** @inheritDoc */
	public function supports(): bool {
		return true;
	}

	/** @inheritDoc */
	public function setPageURL( string $url ): void {
	}
}
