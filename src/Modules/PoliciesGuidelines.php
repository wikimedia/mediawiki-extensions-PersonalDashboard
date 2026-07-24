<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Core content policies, each a Codex card that is also a <details> accordion:
 * the title is the summary, and the definition plus a worked-examples walkthrough
 * disclose inside it.
 *
 * Rendered server-side and CSS-only, so a no-JS visitor gets the whole thing: the
 * accordions open and close with no script. A per-policy subpath opens the
 * matching accordion on load, so a deep link composes the full dashboard with that
 * policy already open; the client router owns anything deeper (which example is
 * showing).
 */
class PoliciesGuidelines extends BaseModule {

	private const MSG_PREFIX = 'personal-dashboard-policies-guidelines-';

	/**
	 * Policies in display order, each mapped to the status icon per example step.
	 * The single source the card body and its accordion steps read from.
	 */
	private const POLICIES = [
		'neutral-point-of-view' => [ 'error', 'success' ],
		'no-original-research' => [ 'error', 'error' ],
		'verifiability' => [ 'warning', 'error', 'success' ],
		'assume-good-faith' => [ 'error', 'success' ],
	];

	public function __construct( IContextSource $context ) {
		parent::__construct( $context );
	}

	/**
	 * The body is server HTML, CSS-only: the accordions need no behavior module.
	 * @return bool
	 */
	protected function serverRendered(): bool {
		return true;
	}

	/**
	 * A per-policy subpath opens the matching accordion server-side, so this
	 * module reads one.
	 * @return bool
	 */
	public function acceptsFocusedSubPath(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( self::MSG_PREFIX . 'title' )->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText() {
		return $this->msg( self::MSG_PREFIX . 'body' )->text();
	}

	/** @inheritDoc */
	protected function getModules() {
		// The accordions are CSS-only, so the no-JS baseline needs no behavior
		// module; the one-example-at-a-time enhancement is a follow-up.
		return [];
	}

	/** @inheritDoc */
	protected function getModuleStyles() {
		return [ 'ext.personalDashboard.policiesGuidelines.styles' ];
	}

	/** @inheritDoc */
	protected function getBody() {
		$cards = '';
		foreach ( self::POLICIES as $name => $steps ) {
			$cards .= $this->renderCard( $name, $steps );
		}
		return Html::rawElement(
			'div',
			[ 'class' => 'personal-dashboard-policies-guidelines__list' ],
			$cards
		);
	}

	/**
	 * One policy as a Codex CSS-only accordion: the title and definition are the
	 * summary header, and the worked-example steps disclose inside. Collapsed by
	 * default; the policy named by the focused subpath opens on load, the
	 * server-side deep link a no-JS visitor lands on.
	 *
	 * @param string $name Policy key
	 * @param string[] $steps Status icon per example step
	 * @return string
	 */
	private function renderCard( string $name, array $steps ): string {
		$attribs = [
			'id' => $name,
			'class' => 'cdx-accordion',
		];
		// Only the policy the subpath names opens; everything else stays collapsed,
		// on the focused page too. The point is not to overwhelm a new user, so we
		// leave the walkthroughs closed until they choose one. The first subpath
		// segment is the policy; anything below it (an example) is the client
		// router's, so match on that segment alone.
		if ( explode( '/', $this->getFocusedSubPath(), 2 )[0] === $name ) {
			$attribs['open'] = '';
		}

		$header = Html::rawElement( 'summary', [],
			Html::rawElement( 'h3', [ 'class' => 'cdx-accordion__header' ],
				Html::element( 'span', [ 'class' => 'cdx-accordion__header__title' ],
					$this->msg( self::MSG_PREFIX . $name . '-title' )->text()
				) .
				Html::element( 'span', [ 'class' => 'cdx-accordion__header__description' ],
					$this->msg( self::MSG_PREFIX . $name . '-definition' )->text()
				)
			)
		);
		$content = Html::rawElement( 'div', [ 'class' => 'cdx-accordion__content' ],
			$this->renderSteps( $name, $steps )
		);

		return Html::rawElement( 'details', $attribs, $header . $content );
	}

	/**
	 * The examples walkthrough stacked inside a policy's accordion: each step's
	 * prompt, the example text, and a status icon.
	 *
	 * @param string $name Policy key
	 * @param string[] $steps Status icon per example step
	 * @return string
	 */
	private function renderSteps( string $name, array $steps ): string {
		$html = '';
		foreach ( $steps as $index => $icon ) {
			$step = $index + 1;
			$html .= Html::rawElement( 'div', [ 'class' => 'personal-dashboard-policies-guidelines__step' ],
				Html::element( 'h4', [],
					$this->msg( self::MSG_PREFIX . 'examples-header' )->params( $step )->text()
				) .
				Html::element( 'p', [],
					$this->msg( self::MSG_PREFIX . $name . '-example-' . $step )->text()
				) .
				Html::rawElement( 'div', [ 'class' => 'personal-dashboard-policies-guidelines__answer' ],
					Html::element( 'span', [ 'class' => [
						'personal-dashboard-policies-guidelines__step-icon',
						'personal-dashboard-policies-guidelines__step-icon--' . $icon,
					] ] ) .
					Html::rawElement( 'div', [ 'class' => 'personal-dashboard-policies-guidelines__answer__text' ],
						Html::element( 'strong', [],
							$this->msg( self::MSG_PREFIX . $name . '-answer-' . $step . '-label' )->text()
						) . ' ' .
						$this->msg( self::MSG_PREFIX . $name . '-answer-' . $step . '-text' )->escaped()
					)
				)
			);
		}
		return $html;
	}
}
