<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Modules;

use MediaWiki\Extension\PersonalDashboard\Modules\FeedLoadingMarkup;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Modules\FeedLoadingMarkup
 */
class FeedLoadingMarkupTest extends MediaWikiUnitTestCase {

	private function newLocalizer(): MessageLocalizer {
		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->method( 'msg' )->willReturnCallback( function ( $key ) {
			$message = $this->createMock( Message::class );
			$message->method( 'text' )->willReturn( 'text-' . $key );
			return $message;
		} );

		return $localizer;
	}

	public function testSummaryWithLoadingCard(): void {
		$html = FeedLoadingMarkup::getHtml( $this->newLocalizer(), 'large' );

		$this->assertSame( 3, substr_count( $html, 'cdx-card--thumbnail-size-large' ) );
		$this->assertStringContainsString( 'disabled', $html );
		$this->assertStringContainsString( 'text-personal-dashboard-feed-load-more', $html );
		$this->assertStringNotContainsString( 'cdx-progress-bar', $html );
	}

	public function testSmallLoadingCardHasNoSizeClass(): void {
		$html = FeedLoadingMarkup::getHtml( $this->newLocalizer(), 'small' );

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
		$html = FeedLoadingMarkup::getHtml( $this->newLocalizer(), $thumbnailSize, $isFocused );

		$this->assertStringContainsString( 'cdx-progress-bar--inline', $html );
		$this->assertStringNotContainsString( 'cdx-card', $html );
	}
}
