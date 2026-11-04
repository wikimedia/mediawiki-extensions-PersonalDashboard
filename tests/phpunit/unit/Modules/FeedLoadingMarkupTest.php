<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Extension\PersonalDashboard\Modules\Trait\FeedLoadingMarkup;
use MediaWiki\Message\Message;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\Trait\FeedLoadingMarkup
 */
class FeedLoadingMarkupTest extends MediaWikiUnitTestCase {

	/**
	 * A feed module that uses the trait, with a settable focused flag and a
	 * public pass-through for the markup under test.
	 *
	 * @return BaseModule
	 */
	private function newModule( bool $isFocused = false ) {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'msg' )->willReturnCallback( function ( $key ) {
			$message = $this->createMock( Message::class );
			$message->method( 'text' )->willReturn( 'text-' . $key );
			return $message;
		} );

		$module = new class( $context ) extends BaseModule {
			use FeedLoadingMarkup;

			protected function getHeaderText(): string {
				return '';
			}

			public function callGetFeedLoadingMarkup( string $thumbnailSize ): string {
				return $this->getFeedLoadingMarkup( $thumbnailSize );
			}
		};
		$module->setFocused( $isFocused );

		return $module;
	}

	public function testSummaryWithLoadingCard(): void {
		$html = $this->newModule()->callGetFeedLoadingMarkup( 'large' );

		$this->assertSame( 3, substr_count( $html, 'cdx-card--thumbnail-size-large' ) );
		$this->assertSame( 3, substr_count( $html, 'cdx-thumbnail__placeholder__icon' ) );
		$this->assertStringContainsString( 'disabled', $html );
		$this->assertStringContainsString( 'text-personal-dashboard-feed-load-more', $html );
		$this->assertStringNotContainsString( 'cdx-progress-bar', $html );
	}

	public function testSmallLoadingCardHasNoSizeClass(): void {
		$html = $this->newModule()->callGetFeedLoadingMarkup( 'small' );

		$this->assertSame( 3, substr_count( $html, 'cdx-card--title-only' ) );
		$this->assertStringNotContainsString( 'cdx-card--thumbnail-size', $html );
	}

	public static function provideProgressBar(): array {
		return [
			'module without a loading card' => [ '', false ],
			'focused page, which shows the full list' => [ 'large', true ],
		];
	}

	/**
	 * @dataProvider provideProgressBar
	 */
	public function testProgressBar( string $thumbnailSize, bool $isFocused ): void {
		$html = $this->newModule( $isFocused )->callGetFeedLoadingMarkup( $thumbnailSize );

		$this->assertStringContainsString( 'cdx-progress-bar--inline', $html );
		// An inline progress bar is aria-hidden in Codex Vue, so this one is too.
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringNotContainsString( 'cdx-card', $html );
	}
}
