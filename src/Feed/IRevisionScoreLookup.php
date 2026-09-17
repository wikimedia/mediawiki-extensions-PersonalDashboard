<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * Machine-learning scores for a set of revisions.
 *
 * This interface exists so the rest of the extension never names an ORES class.
 * PersonalDashboard does not require ORES, so the implementation behind it is
 * either the real one or one that returns nothing, and a caller cannot tell.
 *
 * Scores describe an item; they never select one. Review Changes used to filter
 * recent changes on the score, and T433724 replaces that with a "High revert
 * risk" chip on the card, so what the feed needs is the number, not a verdict.
 */
interface IRevisionScoreLookup {

	/**
	 * Get the scores for a page of revisions.
	 *
	 * The shape matches the Action API's `oresscores`, which the dashboard's
	 * client already knows how to read:
	 *
	 *     [ 4711 => [ 'revertrisklanguageagnostic' => [
	 *         'true' => 0.87, 'false' => 0.13,
	 *     ] ] ]
	 *
	 * A revision with no score is absent from the result rather than present and
	 * empty, so a caller reads it with `?? []`.
	 *
	 * @param int[] $revIds
	 * @return array<int,array<string,array<string,float>>>
	 */
	public function getScores( array $revIds ): array;

	/**
	 * The score at or above which an edit counts as high risk, if the wiki says.
	 *
	 * This is the wiki's own configured threshold, not a verdict about any
	 * edit: the card compares a score against it and decides whether to flag
	 * that edit. Null where the wiki configured none, which the card reads as
	 * "make no check".
	 *
	 *     [ 'model' => 'revertrisklanguageagnostic', 'class' => 'true', 'min' => 0.95 ]
	 *
	 * @return array{model:string,class:string,min:float}|null
	 */
	public function getHighRiskThreshold(): ?array;
}
