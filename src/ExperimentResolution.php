<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard;

use MediaWiki\Extension\TestKitchen\Sdk\ExperimentInterface;

/**
 * Result of ExperimentResolver::resolve(): which module group (if any) an
 * experiment override selected, the variant assigned to every experiment
 * whose assignment took effect this request, and the exposures still owed.
 *
 * Exposure firing is deferred rather than done inside the resolver, because
 * whether an assignment "took effect" can depend on what the resolver's
 * caller does next (e.g. a `pdo` override winning instead). The caller calls
 * sendExposures() only from the branch that actually used this resolution.
 */
final class ExperimentResolution {
	private bool $exposuresSent = false;

	/**
	 * @param ?string $moduleGroup Winning override, or null if none applied
	 * @param array<string, string> $variants Experiment name => assigned
	 *   variant, for every assignment that took effect
	 * @param ExperimentInterface[] $pendingExposures Experiments to expose
	 *   once this resolution is the one that determined the request
	 */
	public function __construct(
		private readonly ?string $moduleGroup,
		private readonly array $variants,
		private readonly array $pendingExposures
	) {
	}

	public static function none(): self {
		return new self( null, [], [] );
	}

	public function getModuleGroup(): ?string {
		return $this->moduleGroup;
	}

	/**
	 * @return array<string, string>
	 */
	public function getVariants(): array {
		return $this->variants;
	}

	/**
	 * Fire sendExposure() for every experiment whose assignment took effect.
	 * A repeat call is a no-op: this resolution shouldn't be able to double-
	 * count if a caller ends up holding onto it and calling this twice.
	 */
	public function sendExposures(): void {
		if ( $this->exposuresSent ) {
			return;
		}
		$this->exposuresSent = true;

		foreach ( $this->pendingExposures as $experiment ) {
			$experiment->sendExposure();
		}
	}
}
