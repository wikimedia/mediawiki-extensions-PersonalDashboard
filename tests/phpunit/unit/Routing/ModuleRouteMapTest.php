<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Routing;

use MediaWiki\Extension\PersonalDashboard\Routing\ModuleRouteMap;
use MediaWiki\Extension\PersonalDashboard\Routing\SlugAliasLookup;
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

	/**
	 * @param array<string,string[]> $aliases Canonical slug => its slugs
	 * @return SlugAliasLookup
	 */
	private function lookup( array $aliases ): SlugAliasLookup {
		$lookup = $this->createMock( SlugAliasLookup::class );
		$lookup->method( 'getAliases' )->willReturn( $aliases );

		return $lookup;
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
	public function testASlugMatchesEverySpellingOfItself( string $segment ) {
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

	public function testALocalizedSlugResolvesAndIsWhatGetsPublished() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
			] ),
			$this->lookup( [ 'impact' => [ 'wirkung', 'auswirkung' ] ] )
		);

		// The content language's first slug is the one the card links to.
		$this->assertSame( 'wirkung', $map->routeFor( 'ext.example.impact' ) );
		// Every slug resolves, the canonical one included, so a link that was
		// minted before the translation keeps working.
		$this->assertSame( 'ext.example.impact', $map->resolve( 'wirkung' ) );
		$this->assertSame( 'ext.example.impact', $map->resolve( 'auswirkung' ) );
		$this->assertSame( 'ext.example.impact', $map->resolve( 'impact' ) );
		$this->assertSame( 'ext.example.impact', $map->resolve( 'ext.example.impact' ) );
	}

	public function testALocalizedSlugIsMatchedUnderscoredAndInAnyCase() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
			] ),
			$this->lookup( [ 'impact' => [ 'tác động' ] ] )
		);

		$this->assertSame( 'ext.example.impact', $map->resolve( 'tác_động' ) );
		$this->assertSame( 'ext.example.impact', $map->resolve( 'Tác Động' ) );
	}

	/**
	 * Canonical slugs are registered before any localized slug, whatever order
	 * the group listed the modules in, so a canonical slug always wins.
	 */
	public function testACanonicalSlugOutranksAnotherModulesAlias() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'ext.one.work', 'enabled' => true, 'slug' => 'work' ],
				[ 'name' => 'ext.two.impact', 'enabled' => true, 'slug' => 'impact' ],
			] ),
			$this->lookup( [ 'work' => [ 'work', 'impact' ] ] )
		);

		$this->assertSame( 'ext.two.impact', $map->resolve( 'impact' ) );
		$this->assertSame( 'duplicate-alias', $map->getConflicts()[0]['reason'] );
	}

	public function testASlugThatWouldEatTheSubPathIsRefused() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'ext.example.impact', 'enabled' => true, 'slug' => 'impact' ],
			] ),
			$this->lookup( [ 'impact' => [ 'my/impact' ] ] )
		);

		// Neither published nor resolvable; the canonical slug carries on.
		$this->assertSame( 'impact', $map->routeFor( 'ext.example.impact' ) );
		$this->assertSame( 'ext.example.impact', $map->resolve( 'impact' ) );
		$this->assertNull( $map->resolve( 'my/impact' ) );
		// Refused once, by pass two. It used to be reported twice, because
		// the published slug was chosen before pass two could refuse it.
		$this->assertSame(
			[ 'malformed-alias' ],
			array_column( $map->getConflicts(), 'reason' )
		);
	}

	public function testASlugSpelledLikeAModuleInTheGroupIsRefused() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'impact', 'enabled' => true ],
				[ 'name' => 'ext.example.work', 'enabled' => true, 'slug' => 'work' ],
			] ),
			$this->lookup( [ 'work' => [ 'work', 'impact' ] ] )
		);

		$this->assertSame( 'impact', $map->resolve( 'impact' ) );
		$this->assertSame( 'malformed-alias', $map->getConflicts()[0]['reason'] );
	}

	/**
	 * A module publishes only a slug that resolves back to itself.
	 *
	 * The preferred slug is whatever the alias file lists first, and pass two
	 * can refuse exactly that one. Publishing it anyway pointed the card at a
	 * route that opened another module, or nothing. Each case below lists the
	 * doomed slug first, which is what makes it a regression test.
	 *
	 * @dataProvider provideUnpublishableFirstSlugs
	 */
	public function testAModulePublishesOnlyASlugThatResolvesBackToIt(
		array $modules, array $aliases, string $module, string $expected
	) {
		$map = ModuleRouteMap::build( $this->groups( $modules ), $this->lookup( $aliases ) );

		$this->assertSame( $expected, $map->routeFor( $module ) );
		// Whatever it publishes has to come back to it.
		$this->assertSame( $module, $map->resolve( $map->routeFor( $module ) ) );
	}

	public static function provideUnpublishableFirstSlugs() {
		$work = [ 'name' => 'ext.one.work', 'enabled' => true, 'slug' => 'work' ];

		return [
			'first slug is another module\'s canonical slug' => [
				[ $work, [ 'name' => 'ext.two.impact', 'enabled' => true, 'slug' => 'impact' ] ],
				[ 'work' => [ 'impact', 'work' ] ],
				'ext.one.work',
				'work',
			],
			'first slug is the name of a module in the group' => [
				[ $work, [ 'name' => 'impact', 'enabled' => true ] ],
				[ 'work' => [ 'impact', 'work' ] ],
				'ext.one.work',
				'work',
			],
			'first slug was already claimed by another module\'s alias' => [
				[
					[ 'name' => 'ext.one.a', 'enabled' => true, 'slug' => 'a' ],
					[ 'name' => 'ext.two.b', 'enabled' => true, 'slug' => 'b' ],
				],
				[ 'a' => [ 'x' ], 'b' => [ 'X' ] ],
				'ext.two.b',
				'b',
			],
			'first slug normalises to nothing' => [
				[ [ 'name' => 'ext.one.a', 'enabled' => true, 'slug' => 'a' ] ],
				[ 'a' => [ '_' ] ],
				'ext.one.a',
				'a',
			],
		];
	}

	/**
	 * The companion to the above: where the preferred slug is fine, it is the
	 * one published, and a later slug does not steal the job.
	 */
	public function testThePreferredSlugIsPublishedWhenNothingRefusesIt() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'ext.one.work', 'enabled' => true, 'slug' => 'work' ],
			] ),
			$this->lookup( [ 'work' => [ 'arbeit', 'werk' ] ] )
		);

		$this->assertSame( 'arbeit', $map->routeFor( 'ext.one.work' ) );
	}

	public function testAModuleWithNoDeclaredSlugGainsNoLocalizedOnes() {
		$map = ModuleRouteMap::build(
			$this->groups( [
				[ 'name' => 'ext.example.impact', 'enabled' => true ],
			] ),
			$this->lookup( [ 'impact' => [ 'wirkung' ] ] )
		);

		$this->assertNull( $map->resolve( 'wirkung' ) );
		$this->assertSame( 'ext.example.impact', $map->routeFor( 'ext.example.impact' ) );
	}
}
