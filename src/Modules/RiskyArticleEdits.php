<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\Title\Title;
use MediaWiki\User\UserEditTracker;
use OOUI\ButtonWidget;

/**
 * Class for the RiskyArticleEdits module.
 */
class RiskyArticleEdits extends BaseModule {

	private \StdClass $moduleConfig;
	private UserEditTracker $userEditTracker;

	/**
	 * @param IContextSource $ctx
	 * @param Config $wikiConfig
	 * @param UserEditTracker $userEditTracker
	 */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		UserEditTracker $userEditTracker
	) {
		parent::__construct( 'riskyArticleEdits', $ctx, $wikiConfig );
		$this->moduleConfig = $wikiConfig->get( 'PersonalDashboardRiskyArticleEdits' );
		$this->userEditTracker = $userEditTracker;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-risky-article-edits-header' )->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return implode( "\n", [
			Html::rawElement( 'div',
				[
					'id' => 'risky-article-edits-vue-root',
					'class' => [ 'ext-personal-dashboard-app-root' ],
				],
			),
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-module-no-js-fallback' )->text()
			),
			$this->getQuestionButton(),
		] );
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return Html::rawElement( 'div',
				[
					'id' => 'risky-article-edits-vue-root--mobile',
				],
			) .
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.riskyArticleEdits' ];
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'chart';
	}

	/** @inheritDoc */
	protected function canRender() {
		// Disabled for this wiki?
		if ( $this->moduleConfig->enabled === false ) {
			return false;
		}

		// Not enough edits?
		$userIdentity = $this->getContext()->getUser();
		$editCount = $this->userEditTracker->getUserEditCount( $userIdentity );
		if ( $this->moduleConfig->minimumUserEdits > $editCount ) {
			return false;
		}

		// Otherwise, render
		return true;
	}

	/**
	 * Get the help desk title and expand the templates and magic words it may contain
	 *
	 * @return null|Title
	 * @throws ConfigException
	 */
	public function getHelpDeskTitle() {
		$helpdeskTitle = $this->moduleConfig->helpdesk;
		if ( $helpdeskTitle === null ) {
			return null;
		}

		// RawMessage is used here to expand magic words like {{#time:o}} - see T213186, T224224
		$msg = new RawMessage( $helpdeskTitle );
		return Title::newFromText( $msg->inContentLanguage()->text() );
	}

	/**
	 * RiskyArticleEdits-specific help desk button
	 * @return null|ButtonWidget
	 */
	private function getQuestionButton() {
		$helpDeskTitle = $this->getHelpDeskTitle();
		if ( $helpDeskTitle === null ) {
			return null;
		}
		return new ButtonWidget( [
			'id' => 'mw-dashboard-cta',
			'classes' => [ 'personal-dashboard-cta' ],
			'active' => false,
			'label' => $this->getContext()
				->msg( 'personal-dashboard-risky-article-edits-question-button' )
				->params( $this->getContext()->getUser()->getName() )
				->text(),
			// nojs action
			'href' => ( $helpDeskTitle )->getLinkURL( [
				'action' => 'edit',
				'section' => 'new',
			] ),
			'infusable' => true,
		] );
	}
}
