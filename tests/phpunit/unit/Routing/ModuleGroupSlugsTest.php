<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Routing;

use MediaWiki\Extension\PersonalDashboard\Routing\ModuleRouteMap;
use MediaWikiUnitTestCase;

/**
 * The dev-time guard on the slugs this extension's own module groups declare.
 *
 * ModuleRouteMap refuses a bad or clashing slug at runtime and the dashboard
 * logs it, because a group is merged from every loaded extension's attributes
 * and one of them can always ship a clash. That is the right behaviour in
 * production and the wrong one in review: a slug this repo gets wrong should
 * fail CI, not quietly stop routing on a wiki. So this reads the declarations
 * in extension.json rather than the merged attribute, and holds this repo to
 * them.
 *
 * @coversNothing
 */
class ModuleGroupSlugsTest extends MediaWikiUnitTestCase {

	/** @return array<string,array> Registered module groups, keyed by id */
	private function moduleGroups(): array {
		$json = json_decode(
			file_get_contents( __DIR__ . '/../../../../extension.json' ), true );

		return $json['attributes']['PersonalDashboard']['ModuleGroups'];
	}

	public function testEverySlugIsWellFormed() {
		foreach ( $this->moduleGroups() as $groupId => $group ) {
			foreach ( $this->modulesOf( $group ) as $module ) {
				if ( !isset( $module['slug'] ) ) {
					continue;
				}

				$this->assertMatchesRegularExpression(
					ModuleRouteMap::SLUG_PATTERN,
					$module['slug'],
					"Slug '{$module['slug']}' in module group '$groupId' is malformed"
				);
			}
		}
	}

	public function testNoTwoModulesInAGroupClaimTheSameSlug() {
		foreach ( $this->moduleGroups() as $groupId => $group ) {
			$map = ModuleRouteMap::build( $group['groups'] );

			$this->assertSame(
				[],
				$map->getConflicts(),
				"Module group '$groupId' declares a slug that cannot be routed"
			);
		}
	}

	/**
	 * A slug is unique within a group, not across them, but the same module in
	 * two groups should still publish one URL: a bookmark has to survive the
	 * experiment or `?pdo=` switch that moved the reader between groups.
	 */
	public function testAModuleKeepsOneSlugAcrossEveryGroup() {
		$seen = [];

		foreach ( $this->moduleGroups() as $groupId => $group ) {
			foreach ( $this->modulesOf( $group ) as $module ) {
				$name = $module['name'];
				$slug = $module['slug'] ?? null;

				if ( !isset( $seen[$name] ) ) {
					$seen[$name] = [ $slug, $groupId ];
					continue;
				}

				[ $first, $firstGroup ] = $seen[$name];
				$this->assertSame(
					$first,
					$slug,
					"Module '$name' routes at a different slug in '$groupId' than in '$firstGroup'"
				);
			}
		}
	}

	/**
	 * @param array $group A registered module group
	 * @return list<array> Every module entry in it
	 */
	private function modulesOf( array $group ): array {
		$modules = [];

		foreach ( $group['groups'] as $topLevel ) {
			foreach ( $topLevel['subgroups'] as $subgroup ) {
				foreach ( $subgroup['modules'] as $module ) {
					$modules[] = $module;
				}
			}
		}

		return $modules;
	}
}
