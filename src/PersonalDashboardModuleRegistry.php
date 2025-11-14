<?php

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\Banner;
use MediaWiki\Extension\PersonalDashboard\Modules\PoliciesGuidelines;
use MediaWiki\Extension\PersonalDashboard\Modules\RiskyArticleEdits;
use MediaWiki\MediaWikiServices;
use OutOfBoundsException;

/**
 * Container class for handling dependency injection of homepage modules.
 */
class PersonalDashboardModuleRegistry {

	private MediaWikiServices $services;

	/** @var callable[]|null id => factory method */
	private ?array $wiring = null;

	/** @var IModule[] id => module */
	private array $modules = [];

	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/**
	 * @param string $id
	 * @param IContextSource $contextSource
	 * @return IModule
	 */
	public function get( string $id, IContextSource $contextSource ): IModule {
		if ( $this->modules[$id] ?? null ) {
			return $this->modules[$id];
		}
		if ( $this->wiring === null ) {
			$this->wiring = self::getWiring();
		}
		if ( !array_key_exists( $id, $this->wiring ) ) {
			throw new OutOfBoundsException( 'Module not found: ' . $id );
		}
		$this->modules[$id] = $this->wiring[$id]( $this->services, $contextSource );
		return $this->modules[$id];
	}

	/**
	 * @internal for testing only
	 * @return string[]
	 */
	public static function getModuleIds(): array {
		return array_keys( self::getWiring() );
	}

	/**
	 * Returns wiring callbacks for each module.
	 * The callback receives the service container and the request context,
	 * and must return a homepage module.
	 * @return callable[] module id => callback
	 */
	private static function getWiring() {
		return [
			'banner' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$dashboardServices = PersonalDashboardServices::wrap( $services );
				return new Banner(
					$context,
					$dashboardServices->getPersonalDashboardWikiConfig(),
				);
			},
			'policiesGuidelines' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$dashboardServices = PersonalDashboardServices::wrap( $services );
				return new PoliciesGuidelines(
					$context,
					$dashboardServices->getPersonalDashboardWikiConfig()
				);
			},
			'riskyArticleEdits' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$dashboardServices = PersonalDashboardServices::wrap( $services );
				return new RiskyArticleEdits(
					$context,
					$dashboardServices->getPersonalDashboardWikiConfig(),
					$services->getUserEditTracker(),
				);
			},
		];
	}

}
