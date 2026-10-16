<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Specials;

use MediaWiki\Extension\PersonalDashboard\PersonalDashboardModuleFactory;
use MediaWiki\Extension\PersonalDashboard\Routing\SlugAliasLookup;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use Wikimedia\Stats\StatsFactory;

/**
 * Everything AbstractSpecialDashboard needs from the service container, in one
 * object.
 *
 * A dashboard's extension.json entry therefore names one service,
 * `PersonalDashboardPageDependencies`, and never has to change again: Personal
 * Dashboard can add a dependency here without touching any subclass's
 * registration. A subclass receives this and hands it to
 * parent::__construct(). It is not meant to read it.
 *
 * @newable
 */
final readonly class DashboardPageDependencies {

	/**
	 * @param PersonalDashboardModuleFactory $moduleFactory
	 * @param StatsFactory $statsFactory
	 * @param ?ExperimentManagerInterface $experimentManager TestKitchen's
	 *   enrollment reader, or null where TestKitchen is not installed
	 * @param ?SlugAliasLookup $slugAliasLookup Localized module route slugs,
	 *   or null to route at the canonical slugs alone
	 */
	public function __construct(
		public PersonalDashboardModuleFactory $moduleFactory,
		public StatsFactory $statsFactory,
		public ?ExperimentManagerInterface $experimentManager = null,
		public ?SlugAliasLookup $slugAliasLookup = null,
	) {
	}
}
