<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Routing;

use MediaWiki\Extension\PersonalDashboard\Routing\ModuleRouteMap;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Routing\ModuleRouteMap
 */
class ModuleRouteMapTest extends MediaWikiUnitTestCase {

	/**
	 * One group holding the given module entries, in the shape a registered
	 * module group declares them.
	 *
	 * @param array $modules Module entries
	 * @return array
	 */
	private function groups( array $modules ): array {
		return [
			[
				'name' => 'main',
				'enabled' => true,
				'subgroups' => [
					[ 'name' => 'primary', 'enabled' => true, 'modules' => $modules ],
				],
			],
		];
	}

	public function testASlugResolvesToItsModule() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
		] ) );

		$this->assertSame( 'ext.example.impact', $map->resolve( 'impact' ) );
		$this->assertSame( 'impact', $map->routeFor( 'ext.example.impact' ) );
		$this->assertSame( [], $map->getConflicts() );
	}

	public function testTheFullModuleNameStillResolves() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
		] ) );

		$this->assertSame( 'ext.example.impact', $map->resolve( 'ext.example.impact' ) );
	}

	public function testAModuleWithNoSlugRoutesAtItsName() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true ],
		] ) );

		$this->assertSame( 'ext.example.impact', $map->resolve( 'ext.example.impact' ) );
		$this->assertSame( 'ext.example.impact', $map->routeFor( 'ext.example.impact' ) );
	}

	public function testAnUnknownSegmentResolvesToNothing() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
		] ) );

		$this->assertNull( $map->resolve( 'nothing-routes-here' ) );
		$this->assertNull( $map->resolve( '' ) );
	}

	public function testRouteForFallsBackToAnUnknownName() {
		$map = ModuleRouteMap::build( $this->groups( [] ) );

		$this->assertSame( 'ext.example.absent', $map->routeFor( 'ext.example.absent' ) );
	}

	/**
	 * A slug arrives underscored from a path URL and spaced from a ?title= one,
	 * in whatever case the link carried.
	 *
	 * @dataProvider provideEquivalentSegments
	 */
	public function testASlugMatchesEveryFormOfItself( string $segment ) {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'my-impact' ],
		] ) );

		$this->assertSame( 'ext.example.impact', $map->resolve( $segment ) );
	}

	public static function provideEquivalentSegments() {
		return [
			'as declared' => [ 'my-impact' ],
			'uppercased' => [ 'MY-IMPACT' ],
			'mixed case' => [ 'My-Impact' ],
			'padded' => [ ' my-impact ' ],
		];
	}

	/**
	 * A module name is matched first and untransformed, so a slug can never take
	 * one over, and the full-name fallback can never be argued away.
	 */
	public function testAModuleNameIsNotMatchedLoosely() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true ],
		] ) );

		$this->assertNull( $map->resolve( 'EXT.EXAMPLE.IMPACT' ) );
	}

	public function testTheFirstClaimOnASlugWins() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.one.impact', 'enabled' => true, 'slug' => 'impact' ],
			[ 'name' => 'ext.two.impact', 'enabled' => true, 'slug' => 'impact' ],
		] ) );

		$this->assertSame( 'ext.one.impact', $map->resolve( 'impact' ) );
		// The loser keeps its own name as a route, so it is still reachable.
		$this->assertSame( 'ext.two.impact', $map->routeFor( 'ext.two.impact' ) );
		$this->assertSame(
			[ [
				'slug' => 'impact',
				'module' => 'ext.two.impact',
				'claimedBy' => 'ext.one.impact',
				'reason' => 'duplicate',
			] ],
			$map->getConflicts()
		);
	}

	/**
	 * @dataProvider provideMalformedSlugs
	 */
	public function testAMalformedSlugIsRefused( $slug ) {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => $slug ],
		] ) );

		$this->assertSame( 'ext.example.impact', $map->routeFor( 'ext.example.impact' ) );
		$this->assertCount( 1, $map->getConflicts() );
		$this->assertSame( 'malformed', $map->getConflicts()[0]['reason'] );
	}

	public static function provideMalformedSlugs() {
		return [
			// A slash would eat the module's own deep sub-path.
			'holds a slash' => [ 'my/impact' ],
			// A dot is what tells a module name apart from a slug.
			'holds a dot' => [ 'my.impact' ],
			'uppercase' => [ 'Impact' ],
			'leading hyphen' => [ '-impact' ],
			'empty' => [ '' ],
			'not a string' => [ 42 ],
		];
	}

	/**
	 * resolve() answers an exact module name before any slug, so a slug spelled
	 * like a module in the same group could never be reached.
	 */
	public function testASlugShadowedByAModuleNameIsRefused() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'impact', 'enabled' => true ],
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
		] ) );

		$this->assertSame( 'impact', $map->resolve( 'impact' ) );
		$this->assertSame( 'ext.example.impact', $map->routeFor( 'ext.example.impact' ) );
		$this->assertSame( 'shadowed-by-module-name', $map->getConflicts()[0]['reason'] );
	}

	public function testAModuleKeepsTheFirstSlugItWasGiven() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
			[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'work' ],
		] ) );

		$this->assertSame( 'impact', $map->routeFor( 'ext.example.impact' ) );
		$this->assertNull( $map->resolve( 'work' ) );
		$this->assertSame( 'remapped', $map->getConflicts()[0]['reason'] );
	}

	/**
	 * The map reads the group config, not the constructed modules: whether a
	 * module renders is the special page's question, asked later.
	 */
	public function testADisabledModuleStillHasARoute() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'name' => 'ext.example.impact', 'enabled' => false, 'slug' => 'impact' ],
		] ) );

		$this->assertSame( 'ext.example.impact', $map->resolve( 'impact' ) );
	}

	public function testAGroupWithNoModulesIsHarmless() {
		$map = ModuleRouteMap::build( [] );

		$this->assertNull( $map->resolve( 'impact' ) );
		$this->assertSame( [], $map->getConflicts() );
	}

	public function testAnEntryWithNoNameIsSkipped() {
		$map = ModuleRouteMap::build( $this->groups( [
			[ 'enabled' => true, 'slug' => 'impact' ],
			[ 'name' => '', 'enabled' => true ],
		] ) );

		$this->assertNull( $map->resolve( 'impact' ) );
	}
}
