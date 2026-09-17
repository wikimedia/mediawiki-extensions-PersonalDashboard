<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Feed\IRevisionScoreLookup;
use MediaWiki\Html\Html;

/**
 * Class for the ReviewChanges module.
 *
 * The feed is not built here: the client reads it from
 * GET /personaldashboard/v0/feed, a page at a time.
 */
class ReviewChanges extends BaseModule {

	public function __construct(
		IContextSource $context,
		private readonly IRevisionScoreLookup $scoreLookup,
	) {
		parent::__construct( $context, shouldWrapModuleWithLink: true );
	}

	/**
	 * The wiki's high-risk threshold, so the card can flag an edit whose score
	 * reaches it. Wiki-wide configuration rather than anything about the
	 * viewer, so it travels in the page instead of the per-viewer feed.
	 *
	 * @inheritDoc
	 */
	public function getJsConfigVars(): array {
		return [
			'wgPersonalDashboardHighRiskThreshold' =>
				$this->scoreLookup->getHighRiskThreshold(),
		];
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		return $this->msg( 'personal-dashboard-risky-article-edits-header' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText(): string {
		return $this->msg( 'personal-dashboard-risky-article-edits-subheader-info' )->text();
	}

	/**
	 * The no-JS fallback footer: a plain link into recent changes, shown on every
	 * viewport when JS is off. With JS the client renders its own detail-branched
	 * footer inside the body slot, so this whole footer section is hidden under the
	 * .client-js no-js-fallback rule.
	 * @inheritDoc
	 */
	protected function getFooter(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
			$this->msg( 'personal-dashboard-risky-article-edits-footer-preamble' )->parse()
		);
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.personalDashboard.reviewChanges' ];
	}
}
