<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * Allows PersonalDashboard to pass CI without TestKitchen.
 */
interface ExperimentManagerInterface {
	public function getExperiment( string $experimentName ): ExperimentInterface;
}
