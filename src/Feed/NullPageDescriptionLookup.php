<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

/**
 * The page description lookup used on a wiki without ShortDescription or Wikibase.
 */
class NullPageDescriptionLookup implements IPageDescriptionLookup {

	/** @inheritDoc */
	public function getDescriptions( array $pageIdentities ): array {
		return [];
	}
}
