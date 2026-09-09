<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Config\Config;
use ORES\Storage\ModelNotFoundError;
use ORES\Storage\StorageScoreLookup;

/**
 * The one class in this extension that names an ORES class.
 *
 * ServiceWiring builds it only when ORES is loaded, so nothing here has to ask
 * whether it is; a wiki without ORES gets NullRevisionScoreLookup instead. It
 * reads scores and never filters on them: see IRevisionScoreLookup.
 *
 * It goes through ORES\Storage\StorageScoreLookup, the documented service
 * interface, rather than joining ores_classification into the feed query. That
 * is the same path ApiHooksHandler::loadScoresForRevisions() takes for
 * rcprop=oresscores, so a client gets the same payload from the feed as from the
 * Action API. A join would instead have to reach ChangesListQuery through
 * legacyMutator(), which core says not to use in new code, and would be repeated
 * once per partition when query partitioning is on.
 */
readonly class OresRevisionScoreLookup implements IRevisionScoreLookup {

	public function __construct(
		private StorageScoreLookup $scoreLookup,
		private OresScoreFormatter $formatter,
		private Config $config,
	) {
	}

	/** @inheritDoc */
	public function getScores( array $revIds ): array {
		if ( !$revIds ) {
			return [];
		}

		$model = $this->getModelName();
		if ( $model === null ) {
			return [];
		}

		try {
			// $models has to be an explicit array. The interface says it may be
			// empty to mean "every model", but the implementation maps over it
			// without checking, so null is a TypeError.
			$rows = $this->scoreLookup->getScores( $revIds, [ $model ] );
		} catch ( ModelNotFoundError ) {
			// Enabled in configuration but never registered in ores_model. The
			// feed is still worth showing without scores.
			return [];
		}

		return $this->formatter->format( $rows, $model, $this->getClasses( $model ) );
	}

	/**
	 * The model to score with, or null if it cannot be used.
	 *
	 * @return string|null
	 */
	private function getModelName(): ?string {
		$model = $this->config->get( 'PersonalDashboardReviewChangesMlModel' );
		if ( !is_string( $model ) || $model === '' ) {
			return null;
		}

		$models = $this->config->get( 'OresModels' );

		return !empty( $models[$model]['enabled'] ) ? $model : null;
	}

	/**
	 * Class name to class id for a model, e.g. [ 'false' => 0, 'true' => 1 ].
	 *
	 * @param string $model
	 * @return array<string,int>
	 */
	private function getClasses( string $model ): array {
		$classes = $this->config->get( 'OresModelClasses' );

		return $classes[$model] ?? [];
	}
}
