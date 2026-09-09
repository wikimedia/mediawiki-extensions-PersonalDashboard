<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Page\PageProps;

readonly class ShortDescriptionPageDescriptionLookup implements IPageDescriptionLookup {

	public function __construct(
		private PageProps $pageProps,
	) {
	}

	/** @inheritDoc */
	public function getDescriptions( array $pageIdentities ): array {
		return $this->pageProps->getProperties( $pageIdentities, 'shortdesc' );
	}
}
