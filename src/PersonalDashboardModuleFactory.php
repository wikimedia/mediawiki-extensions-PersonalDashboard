<?php

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectFactory\ObjectFactory;

class PersonalDashboardModuleFactory {

	/** @var LoggerInterface */
	private $logger;
	/** @var array<string,array> ObjectFactory registry for modules, indexed by module name */
	private array $registry;
	/** @var array<string,IModule> modules indexed by name */
	private array $modules = [];
	private ObjectFactory $objectFactory;

	public function __construct( ExtensionRegistry $extensionRegistry, ObjectFactory $objectFactory ) {
		$this->logger = LoggerFactory::getInstance( 'PersonalDashboard' );
		$this->registry = $extensionRegistry->getAttribute( 'PersonalDashboardModules' );
		$this->objectFactory = $objectFactory;
	}

	/**
	 * Get or construct a new module
	 *
	 * @param string $name Module name (from PersonalDashboard Modules attribute)
	 * @param array $args
	 * @return IModule
	 */
	public function getModule( string $name, array $args ): IModule {
		if ( !array_key_exists( $name, $this->registry ) ) {
			$this->logger->error( "PersonalDashboard: module $name is not supported", [] );
		}
		if ( !array_key_exists( $name, $this->modules ) ) {
			$spec = $this->registry[ $name ];
			$this->modules[ $name ] = $this->objectFactory->createObject(
				$spec,
				[
					'extraArgs' => $args,
					'assertClass' => Modules\BaseModule::class,
				],
			);
		}
		return $this->modules[ $name ];
	}
}
