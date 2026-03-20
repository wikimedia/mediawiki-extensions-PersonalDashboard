<?php

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * Class for the Impact module.
 */
class Impact extends BaseModule {
	private IReadableDatabase $dbr;

	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		IConnectionProvider $connectionProvider
	) {
		parent::__construct( 'impact', $ctx, $wikiConfig, false );
		$this->dbr = $connectionProvider->getReplicaDatabase();
	}

	/**
	 * Query the total number of thanks given by a user.
	 */
	private function getThanksCount( int $actorId ): int {
		if ( $actorId < 1 ) {
			return 0;
		}

		return $this->dbr
			->newSelectQueryBuilder()
			->field( '1' )
			->table( 'logging' )
			->where( [
				'log_action' => 'thank',
				'log_actor' => $actorId,
				'log_deleted' => 0
			] )
			->limit( 1000 )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * Query the total number of edits reviewed by a user.
	 * Calculated by the sum of undos, reverts, patrols, and thanks.
	 */
	private function getReviewCount( int $actorId ): int {
		if ( $actorId < 1 ) {
			return 0;
		}
		$revisionCount = 0;
		$revertTagIds = $this->dbr
			->newSelectQueryBuilder()
			->select( 'ctd_id' )
			->from( 'change_tag_def' )
			->where( [
				'ctd_name' => [ 'mw-undo', 'mw-reverted', 'mw-manual-revert' ]
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		if ( $revertTagIds ) {
			$startTimestamp = wfTimestamp( TimestampFormat::MW, strtotime( '-30 days' ) );
			$revisionCount = $this->dbr
				->newSelectQueryBuilder()
				->field( '1' )
				->table( 'recentchanges' )
				->join( 'change_tag', null, 'ct_rc_id = rc_id' )
				->where( [
					'rc_actor' => $actorId,
					'ct_tag_id' => $revertTagIds,
					$this->dbr->expr( 'rc_timestamp', '>=', $this->dbr->timestamp( $startTimestamp ) )
				] )
				->limit( 1000 )
				->caller( __METHOD__ )
				->fetchRowCount();
		}
		$loggingCount = $this->dbr
			->newSelectQueryBuilder()
			->field( '1' )
			->table( 'logging' )
			->where( [
				'log_action' => [ 'thank', 'patrol', 'approve' ],
				'log_actor' => $actorId,
				'log_deleted' => 0
			] )
			->limit( 1000 )
			->caller( __METHOD__ )
			->fetchRowCount();

		return $revisionCount + $loggingCount;
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->msg( 'personal-dashboard-impact-title' )->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return implode( "\n", [
			Html::rawElement( 'div',
				[
					'id' => 'impact-vue-root',
					'class' => [ 'ext-personal-dashboard-app-root' ],
				],
			),
			Html::element( 'p',
				[ 'class' => 'personal-dashboard-module-no-js-fallback' ],
				$this->msg( 'personal-dashboard-module-no-js-fallback' )->text()
			)
		] );
	}

	/** @inheritDoc */
	protected function getNavIcon() {
		return '';
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		$actorId = $this->getUser()->getActorId();
		$thanksCount = $this->getThanksCount( $actorId );
		$reviewCount = $this->getReviewCount( $actorId );

		return [
			'wgPersonalDashboardImpactThanksCount' => $thanksCount,
			'wgPersonalDashboardImpactReviewCount' => $reviewCount
		];
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.personalDashboard.impact' ];
	}
}
