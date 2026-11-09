<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules\Trait;

use MediaWiki\Html\Html;
use Wikimedia\Codex\Utility\Codex;

/**
 * The server copy of the loading state of FeedPanel.vue, for the
 * getLoadingMarkup() of a feed module. feed.less styles both, so the page does
 * not change when the island replaces this markup.
 *
 * Use this trait in a BaseModule subclass. It builds the markup from the
 * module's own Codex instance, its message localizer, and its focused flag, so
 * the server loading state and the island come from one design system.
 *
 * @since 1.48
 * @stable to use
 */
trait FeedLoadingMarkup {

	/**
	 * The default summaryLimit of FeedPanel: the number of loading cards. The
	 * markup also uses the default show more label. A module that sets either
	 * prop gets a layout shift when its island mounts.
	 *
	 * The name carries the trait prefix because a trait constant collides with a
	 * constant of the same name in the class that uses it.
	 */
	private const int FEED_LOADING_SUMMARY_LIMIT = 3;

	/**
	 * @param string $thumbnailSize The thumbnail size ('small' or 'large') of the
	 *  module's loading card, or '' for a module without a loading card. FeedPanel
	 *  then shows a progress bar, also in the full list.
	 * @return string HTML
	 */
	protected function getFeedLoadingMarkup( string $thumbnailSize = '' ): string {
		// Phan reads a trait on its own, so it needs to be told what uses this one.
		'@phan-var \MediaWiki\Extension\PersonalDashboard\Modules\BaseModule $this';

		$codex = $this->getCodex();

		// The focused page shows the full list, which has no loading cards.
		if ( $thumbnailSize === '' || $this->isFocused() ) {
			return Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed' ],
				self::getFeedLoadingProgressBar( $codex )
			);
		}

		return Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed' ],
			Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed__list' ],
				str_repeat(
					self::getFeedLoadingCard( $codex, $thumbnailSize ),
					self::FEED_LOADING_SUMMARY_LIMIT
				)
			) .
			Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed__show-more-row' ],
				$codex->button()
					->setLabel( $this->msg( 'personal-dashboard-feed-load-more' )->text() )
					->setDisabled( true )
					->setAttributes( [ 'class' => 'personal-dashboard-feed__show-more' ] )
					->getHtml()
			)
		);
	}

	/**
	 * The inline progress bar of FeedPanel, as the CdxProgressBar markup of the
	 * island.
	 *
	 * Codex PHP always writes role="progressbar" with an ARIA label, but an
	 * inline bar in Codex Vue is aria-hidden. This one is too, so the server
	 * copy and the island read the same to assistive technology.
	 *
	 * @param Codex $codex
	 * @return string HTML
	 */
	private static function getFeedLoadingProgressBar( Codex $codex ): string {
		return $codex->progressBar()
			->setInline( true )
			->setAttributes( [ 'aria-hidden' => 'true' ] )
			->getHtml();
	}

	/**
	 * An empty FeedCard with a placeholder thumbnail, as the CdxCard markup of the
	 * island.
	 *
	 * @param Codex $codex
	 * @param string $thumbnailSize The thumbnail size ('small' or 'large')
	 * @return string HTML
	 */
	private static function getFeedLoadingCard( Codex $codex, string $thumbnailSize ): string {
		$classes = [ 'cdx-card--title-only' ];
		// As in CdxCard, the small size has no class.
		if ( $thumbnailSize === 'large' ) {
			$classes[] = 'cdx-card--thumbnail-size-large';
		}
		$classes[] = 'personal-dashboard-feed__card';

		return $codex->card()
			->setThumbnail( $codex->thumbnail() )
			->setAttributes( [ 'class' => implode( ' ', $classes ) ] )
			->getHtml();
	}
}
