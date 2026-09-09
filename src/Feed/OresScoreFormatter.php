<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use stdClass;

/**
 * Turns ores_classification rows into the payload the client reads.
 *
 * Kept apart from OresRevisionScoreLookup, and free of any ORES type, so the
 * three details that are easy to get wrong can be unit tested on a wiki where
 * ORES is not installed — which includes CI, since PersonalDashboard does not
 * require it.
 */
class OresScoreFormatter {

	/**
	 * Build the `oresscores` shape for a page of rows.
	 *
	 * @param iterable<stdClass> $rows Rows of oresc_rev, oresc_class and
	 *   oresc_probability, as ORES\Storage\StorageScoreLookup returns them.
	 * @param string $modelName The model the rows were fetched for.
	 * @param array<string,int> $classes Class name to class id, from
	 *   $wgOresModelClasses, e.g. [ 'false' => 0, 'true' => 1 ].
	 * @return array<int,array<string,array<string,float>>> By revision id.
	 */
	public function format( iterable $rows, string $modelName, array $classes ): array {
		$namesById = array_flip( $classes );

		$byRevision = [];
		foreach ( $rows as $row ) {
			$classId = (int)$row->oresc_class;
			if ( !isset( $namesById[$classId] ) ) {
				continue;
			}

			// oresc_probability is decimal(3,3), which the database hands back as
			// a string. The client compares it against a threshold, so it has to
			// arrive as a number.
			$byRevision[(int)$row->oresc_rev][$namesById[$classId]] =
				(float)$row->oresc_probability;
		}

		$scores = [];
		foreach ( $byRevision as $revId => $byClass ) {
			// Only the positive class is stored, so class 0 has to be worked out.
			// The Action API derives exactly this one and no other, and a client
			// reading both must get the same answer from either.
			if ( isset( $namesById[0] ) && !isset( $byClass[$namesById[0]] ) ) {
				$byClass[$namesById[0]] = 1.0 - array_sum( $byClass );
			}

			$scores[$revId] = [ $modelName => $byClass ];
		}

		return $scores;
	}
}
