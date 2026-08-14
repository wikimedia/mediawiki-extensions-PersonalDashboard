<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManagerInterface;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Stats\StatsFactory;

/**
 * Resolves the module-group override (if any) and health-metrics variant tags
 * for every TestKitchen experiment in a manifest, against one request's
 * enrollment. Independent of any particular special page so a future second
 * PD page can reuse it against its own module-group registry.
 *
 * Does not call sendExposure() itself; see ExperimentResolution.
 */
final readonly class ExperimentResolver {

	/**
	 * @param ?ExperimentManagerInterface $experimentManager TestKitchen's
	 *   enrollment reader, or null if TestKitchen isn't installed
	 * @param StatsFactory $statsFactory
	 * @param array<string, array<string, string>> $manifest Experiments::all()
	 * @param array $moduleGroupRegistry Registered module groups, keyed by ID
	 */
	public function __construct(
		private ?ExperimentManagerInterface $experimentManager,
		private StatsFactory $statsFactory,
		private array $manifest,
		private array $moduleGroupRegistry
	) {
	}

	public function resolve(): ExperimentResolution {
		if ( $this->experimentManager === null ) {
			return ExperimentResolution::none();
		}

		$winningModuleGroup = null;
		$winningExperimentName = null;
		$variants = [];
		$pendingExposures = [];
		$preempted = [];

		foreach ( $this->manifest as $experimentName => $variantMap ) {
			$experiment = $this->experimentManager->getExperiment( $experimentName );
			$assignedGroup = $experiment->getAssignedGroup();

			if ( $assignedGroup === null ) {
				// Not enrolled: nothing to tag, nothing to expose.
				continue;
			}

			$override = $variantMap[ $assignedGroup ] ?? null;

			if ( $override === null ) {
				/*
				 * Tag-only experiment, or a routing experiment's non-overriding
				 * variant (conventionally 'control'): this assignment's effect,
				 * no override, always happens regardless of any other
				 * experiment, so it's exposed unconditionally.
				 */
				$variants[ $experimentName ] = $assignedGroup;
				$pendingExposures[] = $experiment;
				continue;
			}

			if ( !array_key_exists( $override, $this->moduleGroupRegistry ) ) {
				/*
				 * The manifest names a module group this wiki doesn't have: the
				 * extension registering it isn't enabled here, or a deploy
				 * dropped the group before pruning the manifest. No tag, no
				 * exposure, the user won't see this experiment's effect.
				 */
				$this->recordUnroutable( $experimentName, $override );
				continue;
			}

			if ( $winningModuleGroup === null ) {
				// First overriding experiment to resolve to a real group wins
				// the slot: its assignment is what the user actually sees.
				$winningModuleGroup = $override;
				$winningExperimentName = $experimentName;
				$variants[ $experimentName ] = $assignedGroup;
				$pendingExposures[] = $experiment;
			} else {
				/*
				 * A second overriding experiment can't also take effect: there's
				 * one module-group slot. The user never saw its effect, so it
				 * gets neither a tag nor an exposure.
				 */
				$preempted[] = $experimentName;
			}
		}

		if ( $preempted !== [] && $winningExperimentName !== null && $winningModuleGroup !== null ) {
			$this->recordConflicts( $winningExperimentName, $preempted, $winningModuleGroup );
		}

		return new ExperimentResolution( $winningModuleGroup, $variants, $pendingExposures );
	}

	private function recordUnroutable( string $experimentName, string $moduleGroup ): void {
		$this->statsFactory->withComponent( 'PersonalDashboard' )
			->getCounter( 'special_dashboard_experiment_unroutable_total' )
			->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
			->setLabel( 'experiment', $experimentName )
			->setLabel( 'module_group', $moduleGroup )
			->increment();

		Util::logText(
			'TestKitchen experiment variant maps to an unregistered module group',
			[
				'experiment' => $experimentName,
				'moduleGroup' => $moduleGroup,
				'origin' => __METHOD__,
			]
		);
	}

	/**
	 * @param string $winner Experiment name that won the module-group slot
	 * @param string[] $preempted Experiment names that resolved to an override
	 *   but were preempted by $winner
	 * @param string $moduleGroup Module group $winner resolved to
	 */
	private function recordConflicts( string $winner, array $preempted, string $moduleGroup ): void {
		foreach ( $preempted as $preemptedName ) {
			$this->statsFactory->withComponent( 'PersonalDashboard' )
				->getCounter( 'special_dashboard_experiment_routing_conflicts_total' )
				->setLabel( 'wiki', WikiMap::getCurrentWikiId() )
				->setLabel( 'winner', $winner )
				->setLabel( 'preempted', $preemptedName )
				->increment();

			Util::logText(
				'Two TestKitchen experiments resolved a module-group override in the same request',
				[
					'winner' => $winner,
					'preempted' => $preemptedName,
					'moduleGroup' => $moduleGroup,
					'origin' => __METHOD__,
				]
			);
		}
	}
}
