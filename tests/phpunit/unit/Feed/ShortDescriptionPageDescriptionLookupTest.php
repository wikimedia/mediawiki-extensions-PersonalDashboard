<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\ShortDescriptionPageDescriptionLookup;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\PageProps;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\ShortDescriptionPageDescriptionLookup
 */
class ShortDescriptionPageDescriptionLookupTest extends MediaWikiUnitTestCase {

	private function page( int $pageId, string $dbKey ): PageIdentityValue {
		return PageIdentityValue::localIdentity( $pageId, NS_MAIN, $dbKey );
	}

	public function testAsksForTheShortDescriptionPropertyByPage() {
		// The property name is the whole contract with core here, and passing it
		// as a string rather than an array is what makes PageProps return a flat
		// page id to value map instead of one nested by property name.
		$pages = [ $this->page( 7, 'Jupiter' ), $this->page( 9, 'Saturn' ) ];

		$pageProps = $this->createMock( PageProps::class );
		$pageProps->expects( $this->once() )
			->method( 'getProperties' )
			->with( $pages, 'shortdesc' )
			->willReturn( [ 7 => 'Fifth planet from the Sun' ] );

		$lookup = new ShortDescriptionPageDescriptionLookup( $pageProps );

		$this->assertSame( [ 7 => 'Fifth planet from the Sun' ], $lookup->getDescriptions( $pages ) );
	}

	public function testAPageWithNoDescriptionIsSimplyAbsent() {
		// Callers read this with `?? ''`, so leaving a page out is the whole
		// contract for "this page has no description".
		$pageProps = $this->createMock( PageProps::class );
		$pageProps->method( 'getProperties' )->willReturn( [] );

		$lookup = new ShortDescriptionPageDescriptionLookup( $pageProps );

		$this->assertSame( [], $lookup->getDescriptions( [ $this->page( 7, 'Jupiter' ) ] ) );
	}

	public function testNoPagesMeansNoDescriptions() {
		$pageProps = $this->createMock( PageProps::class );
		$pageProps->method( 'getProperties' )->willReturn( [] );

		$lookup = new ShortDescriptionPageDescriptionLookup( $pageProps );

		$this->assertSame( [], $lookup->getDescriptions( [] ) );
	}
}
