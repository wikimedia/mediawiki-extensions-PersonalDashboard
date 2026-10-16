<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Routing;

/**
 * The public routes of one module group.
 *
 * A module has two names, and they are not the same thing:
 *
 *   - the internal id, globally unique: the full namespaced module name. It is
 *     the ObjectFactory key, the ResourceLoader module the client lazy-loads,
 *     and the `pd-slot-<name>` DOM id. Nothing here changes it.
 *   - the public route, unique within a group: the short `slug` a group gives
 *     the module's entry. Module names are namespaced per extension, so two
 *     teams can both want `impact`; scoping the slug to the group lets them
 *     both have it.
 *
 * The internal id resolves as a route as well, which is what keeps a bookmark
 * or a logged URL from before the slug working. See resolve() for the order.
 *
 * A group is assembled from attributes merged across every loaded extension,
 * so a bad or clashing slug is somebody else's deploy, not a programming
 * error. This class therefore refuses such a slug and records it; the caller
 * logs the record. See ../../docs/routing.md.
 */
final class ModuleRouteMap {

	/**
	 * A canonical slug. Lowercase ASCII letters, digits and hyphens, so a slug
	 * can never contain the dots a module name carries, and never the slash
	 * that separates it from a module's own deep sub-path.
	 */
	public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

	/**
	 * @param array<string,true> $names Internal module names in this group
	 * @param array<string,string> $byRoute Normalised slug => internal module name
	 * @param array<string,string> $byModule Internal module name => emitted route
	 * @param list<array<string,string>> $conflicts Refused slugs, for the caller to log
	 */
	private function __construct(
		private readonly array $names,
		private readonly array $byRoute,
		private readonly array $byModule,
		private readonly array $conflicts,
	) {
	}

	/**
	 * Build the map from a resolved module group's `groups` tree.
	 *
	 * Reads the group config rather than the constructed modules, so a module
	 * whose supports() is false still has a route. Whether it renders stays the
	 * special page's question.
	 *
	 * @param array $groups The `groups` tree of a registered module group
	 * @return self
	 */
	public static function build( array $groups ): self {
		$names = [];
		$entries = [];

		foreach ( $groups as $group ) {
			foreach ( (array)( $group['subgroups'] ?? [] ) as $subgroup ) {
				foreach ( (array)( $subgroup['modules'] ?? [] ) as $module ) {
					$name = $module['name'] ?? '';

					if ( !is_string( $name ) || $name === '' ) {
						continue;
					}

					$names[$name] = true;
					$entries[] = [ $name, $module['slug'] ?? null ];
				}
			}
		}

		$byRoute = [];
		$byModule = [];
		$conflicts = [];
		$slugged = [];

		foreach ( $entries as [ $name, $slug ] ) {
			$byModule[$name] ??= $name;

			if ( $slug === null ) {
				continue;
			}

			if ( !is_string( $slug ) || !preg_match( self::SLUG_PATTERN, $slug ) ) {
				$conflicts[] = [
					'slug' => is_string( $slug ) ? $slug : gettype( $slug ),
					'module' => $name,
					'reason' => 'malformed',
				];
				continue;
			}

			if ( isset( $slugged[$name] ) ) {
				// The same module twice in one group, each entry naming a slug.
				// The first one owns the route; this one would silently move it.
				$conflicts[] = [ 'slug' => $slug, 'module' => $name, 'reason' => 'remapped' ];
				continue;
			}

			if ( isset( $names[$slug] ) ) {
				// resolve() answers an exact module name before it looks at any
				// slug, so this slug could never be reached. Refuse it rather
				// than register a route nothing can use.
				$conflicts[] = [ 'slug' => $slug, 'module' => $name, 'reason' => 'shadowed-by-module-name' ];
				continue;
			}

			$key = self::normalize( $slug );

			if ( isset( $byRoute[$key] ) ) {
				$conflicts[] = [
					'slug' => $slug,
					'module' => $name,
					'claimedBy' => $byRoute[$key],
					'reason' => 'duplicate',
				];
				continue;
			}

			$byRoute[$key] = $name;
			$byModule[$name] = $slug;
			$slugged[$name] = true;
		}

		return new self( $names, $byRoute, $byModule, $conflicts );
	}

	/**
	 * The module a URL segment names, or null where it names none.
	 *
	 * Precedence is the whole collision policy: an exact internal module name
	 * answers first, so a slug can never shadow a real module and the full-name
	 * fallback can never be taken away. A slug answers second, case-insensitively
	 * and with underscores and spaces treated alike, because the same segment
	 * reaches us as `tac_dong` from a path URL and `tac dong` from a ?title= one.
	 *
	 * @param string $segment The first subpage segment
	 * @return ?string Internal module name
	 */
	public function resolve( string $segment ): ?string {
		if ( $segment === '' ) {
			return null;
		}

		if ( isset( $this->names[$segment] ) ) {
			return $segment;
		}

		return $this->byRoute[ self::normalize( $segment ) ] ?? null;
	}

	/**
	 * The route this module is published at: its slug where the group gave it
	 * one, its own name otherwise. Both resolve.
	 *
	 * @param string $moduleName Internal module name
	 * @return string
	 */
	public function routeFor( string $moduleName ): string {
		return $this->byModule[$moduleName] ?? $moduleName;
	}

	/**
	 * Slugs this map refused, each with the reason. The caller logs these: a
	 * group can come from any loaded extension, so a clash is operational news
	 * rather than a bug here.
	 *
	 * @return list<array<string,string>>
	 */
	public function getConflicts(): array {
		return $this->conflicts;
	}

	/**
	 * Fold a route segment to its comparison key.
	 *
	 * Language-independent on purpose: Dashboard.vue has to agree with this
	 * byte for byte, and JavaScript has only toLowerCase(). A canonical slug is
	 * ASCII by SLUG_PATTERN, so the Turkish dotless-i case can only reach a
	 * hand-written alias.
	 *
	 * @param string $segment
	 * @return string
	 */
	public static function normalize( string $segment ): string {
		return mb_strtolower( trim( strtr( $segment, '_', ' ' ) ) );
	}
}
