<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;

/**
 * The server copy of the loading state of FeedPanel.vue, for the
 * BaseModule::getLoadingMarkup() of a feed module. feed.less styles both, so the
 * page does not change when the island replaces this markup.
 */
class FeedLoadingMarkup {

	/**
	 * The default summaryLimit of FeedPanel: the number of loading cards. The
	 * markup also uses the default show more label. A module that sets either
	 * prop gets a layout shift when its island mounts.
	 */
	private const SUMMARY_LIMIT = 3;

	/**
	 * @param MessageLocalizer $localizer
	 * @param string $thumbnailSize The thumbnail size ('small' or 'large') of the
	 *  module's loading card, or '' for a module without a loading card. FeedPanel
	 *  then shows a progress bar, also in the full list.
	 * @param bool $isFocused Whether the module renders the focused page, which
	 *  shows the full list
	 * @return string HTML
	 */
	public static function getHtml(
		MessageLocalizer $localizer,
		string $thumbnailSize = '',
		bool $isFocused = false
	): string {
		if ( $thumbnailSize === '' || $isFocused ) {
			return Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed' ],
				self::getProgressBar()
			);
		}

		return Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed' ],
			Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed__list' ],
				str_repeat( self::getCard( $thumbnailSize ), self::SUMMARY_LIMIT )
			) .
			Html::rawElement( 'div', [ 'class' => 'personal-dashboard-feed__show-more-row' ],
				Html::element( 'button', [
					'class' => [ 'cdx-button', 'personal-dashboard-feed__show-more' ],
					'disabled' => true,
				], $localizer->msg( 'personal-dashboard-feed-load-more' )->text() )
			)
		);
	}

	private static function getProgressBar(): string {
		return Html::rawElement( 'div', [
			'class' => [ 'cdx-progress-bar', 'cdx-progress-bar--inline' ],
		], Html::element( 'div', [ 'class' => 'cdx-progress-bar__bar' ] ) );
	}

	/**
	 * An empty FeedCard with a placeholder thumbnail, as the CdxCard markup of the
	 * island.
	 */
	private static function getCard( string $thumbnailSize ): string {
		return Html::rawElement( 'span', [
			'class' => [
				'cdx-card',
				'cdx-card--title-only',
				// As in CdxCard, the small size has no class.
				'cdx-card--thumbnail-size-large' => $thumbnailSize === 'large',
				'personal-dashboard-feed__card',
			],
		], Html::rawElement( 'span', [ 'class' => [ 'cdx-thumbnail', 'cdx-card__thumbnail' ] ],
			Html::rawElement( 'span', [ 'class' => 'cdx-thumbnail__placeholder' ],
				Html::element( 'span', [ 'class' => 'cdx-thumbnail__placeholder__icon' ] )
			)
		) . Html::rawElement( 'span', [ 'class' => 'cdx-card__text' ],
			Html::element( 'span', [ 'class' => 'cdx-card__text__title' ] )
		) );
	}
}
