<?php

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use OutOfBoundsException;

/**
 * Container class for handling dependency injection of homepage modules.
 */
class PersonalDashboardModuleRegistry {

	private MediaWikiServices $services;

	/** @var IModule[] id => module */
	private array $modules = [];

	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/**
	 * @param string $id
	 * @param IContextSource|null $context
	 * @return IModule
	 */
	public function getModule( string $id, ?IContextSource $context ): IModule {
		if ( $this->modules[$id] ?? null ) {
			return $this->modules[$id];
		}
		if ( !in_array( $id, self::getModuleIds() ) ) {
			throw new OutOfBoundsException( 'Module not found: ' . $id );
		}

		$moduleService = $this->services->getService( $id );
		if ( $context !== null ) {
			$moduleService->setContext( $context );
		}
		$this->modules[$id] = $moduleService;
		return $this->modules[$id];
	}

	/**
	 * @internal for testing only
	 * @return string[]
	 */
	public static function getModuleIds(): array {
		return ExtensionRegistry::getInstance()->getAttribute( 'PersonalDashboardModules' );
	}

}
