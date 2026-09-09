<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\TitleFactory;
use Wikibase\Client\Store\DescriptionLookup;

readonly class WikibasePageDescriptionLookup implements IPageDescriptionLookup {

	public function __construct(
		private DescriptionLookup $descriptionLookup,
		private TitleFactory $titleFactory,
		private LinkBatchFactory $linkBatchFactory,
	) {
	}

	/** @inheritDoc */
	public function getDescriptions( array $pageIdentities ): array {
		if ( !$pageIdentities ) {
			return [];
		}

		/*
		 * Wikibase reads a page language off every Title it is handed, and ours
		 * are built from recentchanges rows with no page row behind them, so
		 * each would otherwise fetch its own. The lookup reads through the
		 * LinkCache, so filling it once up front covers the whole page of items.
		 */
		$this->linkBatchFactory->newLinkBatch( $pageIdentities )
			->setCaller( __METHOD__ )
			->execute();

		// Wikibase wants Titles, not PageIdentities.
		$titles = array_map(
			fn ( PageIdentity $pageIdentity ) => $this->titleFactory->newFromPageIdentity( $pageIdentity ),
			$pageIdentities
		);

		return $this->descriptionLookup->getDescriptions( $titles, [
			DescriptionLookup::SOURCE_LOCAL, DescriptionLookup::SOURCE_CENTRAL
		] );
	}
}
