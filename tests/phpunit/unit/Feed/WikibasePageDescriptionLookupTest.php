<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Feed;

use MediaWiki\Extension\PersonalDashboard\Feed\WikibasePageDescriptionLookup;
use MediaWiki\Page\LinkBatch;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use Wikibase\Client\Store\DescriptionLookup;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Feed\WikibasePageDescriptionLookup
 */
class WikibasePageDescriptionLookupTest extends MediaWikiUnitTestCase {

	/** @var list<string> Which collaborator ran, in the order it ran. */
	private array $calls = [];

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		/*
		 * Wikibase is an optional dependency and is not one of ours for PHPUnit,
		 * so skipping instead would leave the batching below unexercised
		 * everywhere. The Phan stub already declares the signature we mock, and
		 * Phan does check that against the real class.
		 */
		if ( !class_exists( DescriptionLookup::class ) ) {
			require_once __DIR__ . '/../../../../.phan/stubs/DescriptionLookup.php';
		}
	}

	private function page( int $pageId, string $dbKey ): PageIdentityValue {
		return PageIdentityValue::localIdentity( $pageId, NS_MAIN, $dbKey );
	}

	private function newTitleFactory(): TitleFactory {
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromPageIdentity' )
			->willReturn( $this->createMock( Title::class ) );

		return $titleFactory;
	}

	/**
	 * @param PageIdentityValue[] $expectedPages The batch has to cover the whole
	 *   page of items, so the set it gets is the assertion.
	 */
	private function newLinkBatchFactory( array $expectedPages ): LinkBatchFactory {
		$batch = $this->createMock( LinkBatch::class );
		$batch->method( 'setCaller' )->willReturnSelf();
		$batch->expects( $this->once() )
			->method( 'execute' )
			->willReturnCallback( function (): array {
				$this->calls[] = 'batch';
				return [];
			} );

		$linkBatchFactory = $this->createMock( LinkBatchFactory::class );
		$linkBatchFactory->expects( $this->once() )
			->method( 'newLinkBatch' )
			->with( $expectedPages )
			->willReturn( $batch );

		return $linkBatchFactory;
	}

	/**
	 * @param int $expectedTitles
	 * @param string[] $return
	 */
	private function newDescriptionLookup( int $expectedTitles, array $return ): DescriptionLookup {
		$descriptionLookup = $this->createMock( DescriptionLookup::class );
		$descriptionLookup->expects( $this->once() )
			->method( 'getDescriptions' )
			->with(
				$this->countOf( $expectedTitles ),
				[ DescriptionLookup::SOURCE_LOCAL, DescriptionLookup::SOURCE_CENTRAL ]
			)
			->willReturnCallback( function ( array $titles, $sources ) use ( $return ): array {
				$this->calls[] = 'descriptions';
				return $return;
			} );

		return $descriptionLookup;
	}

	public function testItCastsEveryPageAndAsksBothSources() {
		// Local alone is the wikibase-shortdesc property, and the central source
		// is where most pages actually keep a description.
		$pages = [ $this->page( 7, 'Jupiter' ), $this->page( 9, 'Saturn' ) ];

		$lookup = new WikibasePageDescriptionLookup(
			$this->newDescriptionLookup( 2, [ 7 => 'Fifth planet from the Sun' ] ),
			$this->newTitleFactory(),
			$this->newLinkBatchFactory( $pages )
		);

		// Saturn comes back absent rather than present and empty, which is what
		// lets the caller read the result with `?? ''`.
		$this->assertSame(
			[ 7 => 'Fifth planet from the Sun' ],
			$lookup->getDescriptions( $pages )
		);
	}

	public function testItFillsTheLinkCacheBeforeAsking() {
		// Wikibase reads a page language off every Title, and these have no page
		// row behind them, so a batch running afterwards would be too late to
		// save the per-item fetches.
		$pages = [ $this->page( 7, 'Jupiter' ), $this->page( 9, 'Saturn' ) ];

		$lookup = new WikibasePageDescriptionLookup(
			$this->newDescriptionLookup( 2, [] ),
			$this->newTitleFactory(),
			$this->newLinkBatchFactory( $pages )
		);

		$lookup->getDescriptions( $pages );

		$this->assertSame( [ 'batch', 'descriptions' ], $this->calls );
	}

	public function testNoPagesAsksNothing() {
		$descriptionLookup = $this->createMock( DescriptionLookup::class );
		$descriptionLookup->expects( $this->never() )->method( 'getDescriptions' );

		$linkBatchFactory = $this->createMock( LinkBatchFactory::class );
		$linkBatchFactory->expects( $this->never() )->method( 'newLinkBatch' );

		$lookup = new WikibasePageDescriptionLookup(
			$descriptionLookup,
			$this->newTitleFactory(),
			$linkBatchFactory
		);

		$this->assertSame( [], $lookup->getDescriptions( [] ) );
	}
}
