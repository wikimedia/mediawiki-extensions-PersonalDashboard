<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Modules;

use MediaWiki\ChangeTags\ChangeTags;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQuery;
use MediaWiki\RecentChanges\ChangesListQuery\ChangesListQueryFactory;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * Class for the ReviewChanges module.
 */
class ReviewChanges extends BaseModule {

	private const int RECENTLY_EDITED_PAGES_LIMIT = 50;
	private const int REVIEW_CHANGES_LIMIT = 100;
	private const array EXCLUDED_TAGS = [
		ChangeTags::TAG_REVERTED,
		ChangeTags::TAG_UNDO,
		ChangeTags::TAG_ROLLBACK,
	];

	private IReadableDatabase $dbr;

	public function __construct(
		IContextSource $context,
		IConnectionProvider $connectionProvider,
		private readonly ChangesListQueryFactory $changesListQueryFactory,
		private readonly CommentFormatter $commentFormatter,
	) {
		$this->dbr = $connectionProvider->getReplicaDatabase();
		parent::__construct( $context, shouldWrapModuleWithLink: true );
	}

	/**
	 * Fetch recent changes to pages that the user has recently edited,
	 * excluding their own edits, bot edits, and reverted edits.
	 *
	 * @return array FeedItem-format to be consumed by the useRecentlyEditedFeed composable.
	 */
	private function getRecentlyEditedItems(): array {
		$actorId = $this->getUser()->getActorId();
		if ( !$actorId ) {
			return [];
		}

		$pagesRecentlyEdited = $this->dbr->newSelectQueryBuilder()
			->distinct()
			->select( 'rev_page' )
			->from( 'revision' )
			->join( 'page', null, [ 'rev_page = page_id' ] )
			->where( [
				'rev_actor' => $actorId,
				'page_namespace' => 0,
			] )
			->orderBy( 'rev_timestamp DESC' )
			->limit( self::RECENTLY_EDITED_PAGES_LIMIT )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( !$pagesRecentlyEdited ) {
			return [];
		}

		$result = $this->changesListQueryFactory->newQuery()
			->audience( $this->getUser() )
			->recentChangeFields()
			->addChangeTagSummaryField()
			->requireLatest()
			->excludeUser( $this->getUser() )
			->excludeChangeTags( self::EXCLUDED_TAGS )
			->excludeDeletedUser()
			->excludeDeletedLogAction()
			->where( $this->dbr->andExpr( [
				$this->dbr->expr( 'rc_cur_id', '=', $pagesRecentlyEdited ),
				$this->dbr->expr( 'rc_source', '=', RecentChange::SRC_EDIT ),
				$this->dbr->expr( 'rc_bot', '=', 0 ),
			] ) )
			->orderBy( ChangesListQuery::SORT_TIMESTAMP_DESC )
			->limit( self::REVIEW_CHANGES_LIMIT )
			->caller( __METHOD__ )
			->fetchResult()
			->getResultWrapper();

		// Reconstruct the result into the FeedItem format consumable by <list-card> in Vue.
		$revs = [];
		foreach ( $result as $row ) {
			// TODO: Clientside should be aware the summary is hidden
			//   and show a muted 'revdelete-summary-hid' message
			$parsedComment = '';
			if ( RevisionRecord::userCanBitfield(
				$row->rc_deleted, RevisionRecord::DELETED_COMMENT, $this->getUser() )
			) {
				$parsedComment = $this->commentFormatter->format( $row->rc_comment_text ?? '' );
			}

			$revs[] = [
				'title' => Title::makeTitle( $row->rc_namespace, $row->rc_title )->getPrefixedText(),
				'revid' => $row->rc_this_oldid,
				'pageid' => $row->rc_cur_id,
				'old_revid' => $row->rc_last_oldid,
				'user' => $row->rc_user_text,
				'timestamp' => wfTimestamp( TimestampFormat::ISO_8601, $row->rc_timestamp ),
				'newlen' => $row->rc_new_len,
				'oldlen' => $row->rc_old_len,
				'parsedcomment' => $parsedComment,
				'minor' => (bool)$row->rc_minor,
				'bot' => (bool)$row->rc_bot,
				'new' => $row->rc_source === RecentChange::SRC_NEW,
				'tags' => $row->ts_tags ? explode( ',', $row->ts_tags ) : [],
			];
		}

		return $revs;
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
	public function getJsConfigVars(): array {
		$recentlyEditedItems = $this->getRecentlyEditedItems();

		// fallback to ml disabled if ores isn't loaded and configured as expected
		$config = $this->getConfig();
		$mlDisabledConf = [
			'wgPersonalDashboardReviewChangesMlEnabled' => false,
			'wgPersonalDashboardReviewChangesExcludedTags' => self::EXCLUDED_TAGS,
			'wgPersonalDashboardRecentlyEditedItems' => $recentlyEditedItems,
		];
		if (
			!ExtensionRegistry::getInstance()->isLoaded( 'ORES' ) ||
			!$config->has( 'OresUiEnabled' ) || !$config->get( 'OresUiEnabled' ) ||
			!$config->has( 'OresFiltersThresholds' ) ||
			!$config->has( 'OresModels' )
		) {
			return $mlDisabledConf;
		}

		// Provide ML model threshold configuration from ORES extension if avaiable
		$thresholds = $config->get( 'OresFiltersThresholds' );
		$oresModels = $config->get( 'OresModels' );

		// use a predefined filter for models we allow
		$filters = [
			'revertrisklanguageagnostic' => 'revertrisk',
			'damaging' => 'likelybad',
		];
		// get model from url param or config
		$models = [];
		$requestedModel = $this->getContext()->getRequest()->getText( 'reviewchanges_mlmodel' );
		if ( $requestedModel !== '' ) {
			$models[] = $requestedModel;
		}
		$models[] = $config->get( 'PersonalDashboardReviewChangesMlModel' );

		// try models in decending order
		// make the model avaiable if it is enabled and the expected filter is configured
		foreach ( $models as $model ) {
			if (
				// model conf: model key exists
				!array_key_exists( $model, $oresModels ) ||
				// model conf: model enablement key exists
				!array_key_exists( 'enabled', $oresModels[ $model ] ) ||
				// model conf: model enabled
				$oresModels[ $model ][ 'enabled' ] !== true ||
				// allowed filters: model key exists
				!array_key_exists( $model, $filters ) ||
				// thresholds conf: model key exists
				!array_key_exists( $model, $thresholds ) ||
				// thresholds conf: filter key exists
				!array_key_exists( $filters[ $model ], $thresholds[ $model ] )
			) {
				continue;
			}
			return [
				'wgPersonalDashboardReviewChangesMlModel' => $model,
				'wgPersonalDashboardReviewChangesMlEnabled' => true,
				'wgPersonalDashboardReviewChangesExcludedTags' => self::EXCLUDED_TAGS,
				'wgPersonalDashboardRecentlyEditedItems' => $recentlyEditedItems,
			];
		}
		// fallback to ml disabled if no model is available
		return $mlDisabledConf;
	}

	/** @inheritDoc */
	protected function getModules(): array {
		return [ 'ext.personalDashboard.reviewChanges' ];
	}
}
