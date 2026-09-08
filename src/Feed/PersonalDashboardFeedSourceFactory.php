<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * Builds feed sources from the PersonalDashboard.FeedSources attribute.
 *
 * This mirrors PersonalDashboardModuleFactory: it reads declarations, it does
 * not accept them at runtime. Any extension registers a source by declaring the
 * attribute in its own extension.json, so a new source needs no change here.
 * See ./docs/decisions.md for why registration stays declarative.
 *
 * One difference from the module factory: there is no placeholder source. An
 * unregistered name returns null and logs. A feed that loses one source renders
 * shorter, which is better than a stand-in item in a list of real ones. A module
 * that loses its class has no such option, because its card is already on the
 * page.
 */
class PersonalDashboardFeedSourceFactory {

	/** @var array<string,array> ObjectFactory registry for feed sources, indexed by source name */
	private array $registry;

	/** @var array<string,IFeedSource> Feed sources indexed by name */
	private array $sources = [];

	public function __construct(
		ExtensionRegistry $extensionRegistry,
		private readonly ObjectFactory $objectFactory,
		private readonly LoggerInterface $logger,
	) {
		$this->registry = $extensionRegistry->getAttribute( 'PersonalDashboardFeedSources' );
	}

	/**
	 * Get a feed source by name.
	 *
	 * The source is built once and kept, so two callers in one request share an
	 * instance.
	 *
	 * @param string $name Feed source name, from the PersonalDashboard.FeedSources attribute
	 * @return IFeedSource|null Null if nothing is registered under $name
	 */
	public function getSource( string $name ): ?IFeedSource {
		if ( !array_key_exists( $name, $this->registry ) ) {
			$this->logger->error(
				'PersonalDashboard: feed source {name} is not registered',
				[ 'name' => $name ]
			);
			return null;
		}

		if ( !array_key_exists( $name, $this->sources ) ) {
			$source = $this->objectFactory->createObject(
				$this->registry[ $name ],
				[ 'assertClass' => IFeedSource::class ],
			);
			$source->setName( $name );
			$this->sources[ $name ] = $source;
		}

		return $this->sources[ $name ];
	}

	/**
	 * Get the name of every registered feed source.
	 *
	 * A caller validates a requested name against this list before it asks for
	 * the source, so a typo fails loudly instead of logging an error.
	 *
	 * @return string[]
	 */
	public function getSourceNames(): array {
		return array_keys( $this->registry );
	}
}
