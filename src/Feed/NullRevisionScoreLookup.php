<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * The score lookup used on a wiki without ORES.
 *
 * PersonalDashboard does not require ORES, so scoring has to be absent rather
 * than broken. Returning nothing here means every caller works unchanged and
 * none of them needs to ask whether ORES is installed.
 */
class NullRevisionScoreLookup implements IRevisionScoreLookup {

	/** @inheritDoc */
	public function getScores( array $revIds ): array {
		return [];
	}
}
