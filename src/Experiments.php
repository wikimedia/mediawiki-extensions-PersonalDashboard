<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard;

/**
 * Manifest of TestKitchen experiments PersonalDashboard resolves module groups
 * and health-metrics variant tags against. Adding an experiment is a one-line
 * addition to MANIFEST.
 *
 * Each entry's value is a variant => module group map containing only the
 * variants that override the baseline. A variant absent from that map
 * (conventionally 'control') falls through to normal baseline resolution; it
 * is never given a literal group name, since the baseline isn't always
 * 'default' once eligibility varies it. An empty map ([]) registers a
 * tag-only experiment: it never competes for the module-group slot, it's
 * only checked so its assignment can be recorded and exposed.
 *
 * Order is arbitration order: when two experiments' variants both resolve to
 * a registered override in the same request, the first one listed wins.
 */
class Experiments {
	private const MANIFEST = [
		'T426615' => [
			'treatment' => 'T426615',
		],
	];

	/**
	 * @return array<string, array<string, string>> Manifest, keyed by
	 *   experiment name, in resolution order.
	 */
	public static function all(): array {
		return self::MANIFEST;
	}
}
