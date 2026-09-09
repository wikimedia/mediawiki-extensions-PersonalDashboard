<?php

declare( strict_types = 1 );

namespace Wikibase\Client\Store;

use MediaWiki\Title\Title;

/**
 * Lets phan run where Wikibase is not installed.
 *
 * This mirrors the part of the real class WikibasePageDescriptionLookup calls.
 * A stub asserts a shape rather than checks it, so it cannot see a signature
 * that drifted. CI installs Wikibase, and .phan/config.php drops this file when
 * the real one is present. CI is therefore the check.
 */
class DescriptionLookup {

	public const SOURCE_LOCAL = 'local';
	public const SOURCE_CENTRAL = 'central';

	/**
	 * @param Title[] $titles
	 * @param string|string[] $sources
	 * @param string[]|null &$actualSources
	 * @return string[] Page ID to description.
	 */
	public function getDescriptions( array $titles, $sources, &$actualSources = null ) {
		return [];
	}
}
