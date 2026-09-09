<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Page\PageIdentity;

/**
 * Page descriptions for a set of pages.
 *
 * This interface exists to maintain Extension:Wikibase and ShortDescription as
 * optional dependencies. The implementation behind it is either the real one
 * or one that returns nothing, and a caller cannot tell.
 *
 * Short descriptions describe a page associated with a feed item.
 */
interface IPageDescriptionLookup {

	/**
	 * Get the short descriptions for the given pages.
	 *
	 * @param PageIdentity[] $pageIdentities
	 * @return string[] Associative array of page ID => description.
	 *   Pages with no description will be omitted.
	 */
	public function getDescriptions( array $pageIdentities ): array;
}
