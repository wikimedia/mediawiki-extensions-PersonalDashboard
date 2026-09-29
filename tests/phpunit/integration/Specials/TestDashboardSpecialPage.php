<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Specials;

use MediaWiki\Extension\PersonalDashboard\Specials\AbstractSpecialDashboard;
use MediaWiki\Extension\PersonalDashboard\Specials\DashboardPageDependencies;
use MediaWiki\Title\Title;

/**
 * A second dashboard, which exists to prove AbstractSpecialDashboard serves
 * one: another page name, another module group, its own StatsFactory
 * component, and, unless a test says otherwise, whatever the base class
 * defaults to.
 *
 * Every answer is a constructor argument, so one class covers each scenario.
 * Those arguments are promoted properties, so PHP assigns them before
 * parent::__construct() runs and getPageName() can answer from the base
 * constructor. That makes this fixture a regression test for the rule in
 * AbstractSpecialDashboard's docblock.
 */
class TestDashboardSpecialPage extends AbstractSpecialDashboard {

	public function __construct(
		DashboardPageDependencies $dependencies,
		private readonly string $pageName = 'PersonalDashboardTestDouble',
		private readonly string $moduleGroup = 'testDouble',
		private readonly string $statsComponent = 'PersonalDashboardTestDouble',
		private readonly ?array $experimentManifest = null,
		private readonly ?Title $backLinkTarget = null,
	) {
		parent::__construct( $dependencies );
	}

	/** @inheritDoc */
	protected function getPageName(): string {
		return $this->pageName;
	}

	/** @inheritDoc */
	protected function getBaselineModuleGroup(): string {
		return $this->moduleGroup;
	}

	/** @inheritDoc */
	protected function getStatsComponent(): string {
		return $this->statsComponent;
	}

	/** @inheritDoc */
	protected function getExperimentManifest(): array {
		return $this->experimentManifest ?? parent::getExperimentManifest();
	}

	/** @inheritDoc */
	protected function getBackLinkTarget(): Title {
		return $this->backLinkTarget ?? parent::getBackLinkTarget();
	}
}
