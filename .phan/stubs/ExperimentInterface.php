<?php

namespace MediaWiki\Extension\TestKitchen\Sdk;

/**
 * Allows PersonalDashboard to pass CI without TestKitchen.
 */
interface ExperimentInterface {
	public function getAssignedGroup(): ?string;

	public function isAssignedGroup( string ...$groups ): bool;

	public function send(
		string $action,
		array $interactionData = [],
		array $contextualAttributes = []
	): void;

	public function sendExposure(): void;
}
