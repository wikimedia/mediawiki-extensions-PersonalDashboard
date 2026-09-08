<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Rest;

use InvalidArgumentException;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedContinuation;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedMerger;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedSourceResult;
use MediaWiki\Extension\PersonalDashboard\Feed\IFeedItem;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\LocalizedHttpException;
use Psr\Log\LoggerInterface;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * GET /personaldashboard/v0/feed
 *
 * Answers one request with items from several feed sources, merged newest
 * first, plus a token that resumes where this page stopped. The dashboard's
 * feed modules used to do this work in the browser, one Action API query per
 * source with no way to page past the first batch.
 *
 * The response is per-viewer, so it is uncacheable by design. Nothing here sets
 * cache headers: Handler::applyCacheControl() already marks a response private
 * whenever the session is persistent.
 */
class FeedHandler extends Handler {

	private const int DEFAULT_LIMIT = 10;
	private const int MAX_LIMIT = 50;

	/**
	 * The feed a caller gets when it names no sources.
	 *
	 * Recent changes is the widest source and the one least shaped by who is
	 * asking, which makes it the least surprising thing to answer with.
	 */
	private const string DEFAULT_SOURCE = 'recentchanges';

	public function __construct(
		private readonly PersonalDashboardFeedSourceFactory $feedSourceFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * A safe read. Without this the route is refused on a read-only wiki, and
	 * treated as unsafe for CORS, because the base class assumes writes.
	 *
	 * @inheritDoc
	 */
	public function needsWriteAccess(): bool {
		return false;
	}

	/**
	 * @return array
	 * @throws LocalizedHttpException
	 */
	public function execute(): array {
		$authority = $this->getAuthority();
		// isNamed(), not isRegistered(): a temporary account has no watchlist and
		// no edits of its own to build a feed from.
		if ( !$authority->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-permission-denied-anon' ),
				401
			);
		}

		$params = $this->getValidatedParams();
		$limit = $params['limit'];
		// Already deduplicated: ParamValidator drops repeats from a multi-value
		// parameter unless PARAM_ALLOW_DUPLICATES says otherwise, so one source
		// cannot be named twice into two shares of the feed.
		$sources = $params['sources'] ?: [ self::DEFAULT_SOURCE ];

		$continuation = $this->readContinuation( $params['continue'], $sources );
		$cursors = $continuation?->cursors;
		$live = $cursors === null
			? $sources
			: array_values( array_filter(
				$sources,
				static fn ( string $name ) => array_key_exists( $name, $cursors )
			) );

		$results = [];
		foreach ( $live as $name ) {
			$source = $this->feedSourceFactory->getSource( $name );
			// Validation already rejected unregistered names, so this is only
			// reachable if a source disappeared between the two calls.
			$results[$name] = $source === null
				? FeedSourceResult::newEmpty()
				: $source->getItems( new FeedRequest(
					$authority,
					// Every source is asked for the whole limit, not its share of
					// it. One source has to be able to fill the page when the
					// others come up dry, which is the point of the merge giving
					// away the share of a source that runs out.
					$limit,
					$cursors[$name] ?? null
				) );
		}

		// The token names everything the walk has served. Without it a source
		// whose copy of an item the merge never reached would offer that copy on
		// a later page, after another source already showed it.
		$merged = ( new FeedMerger() )->merge(
			$results, $limit, $continuation?->served ?? []
		);

		$response = [
			'items' => array_map(
				static fn ( IFeedItem $item ) => $item->toArray(),
				$merged->items
			),
		];

		$next = [];
		$movedOn = false;
		foreach ( $live as $name ) {
			$movedOn = $movedOn || $merged->cursors[$name] !== null;

			if ( $merged->hasMore[$name] ) {
				// A source that gave nothing to this page keeps the cursor it
				// came in with, so it resumes where it truly left off.
				$next[$name] = $merged->cursors[$name] ?? ( $cursors[$name] ?? null );
			}
		}
		// Offer to continue only if this page moved at least one source forward.
		// A source that cannot tell cheaply must report that more may follow, so a
		// page can select nothing and still hear that. Every cursor then stays as
		// it came in, and the token would ask the same question again, which a
		// client would follow for as long as the answer stays empty.
		if ( $next && $movedOn ) {
			// Everything the walk has served, oldest first, not only this page.
			// A source can still be holding a copy of something from several
			// pages back, and the token drops from the front when it has to.
			$served = array_values( array_unique( array_merge(
				$continuation?->served ?? [],
				$merged->servedKeys
			) ) );

			$response['continue'] = ( new FeedContinuation( $next, $served ) )
				->encode( $sources );
		}

		return $response;
	}

	/**
	 * @param ?string $token
	 * @param string[] $sources
	 * @return ?FeedContinuation Null when starting from the newest items,
	 *   otherwise where every source still in play resumes, and what the page
	 *   before this one served.
	 * @throws LocalizedHttpException
	 */
	private function readContinuation( ?string $token, array $sources ): ?FeedContinuation {
		if ( $token === null ) {
			return null;
		}

		try {
			return FeedContinuation::decode( $token, $sources );
		} catch ( InvalidArgumentException $e ) {
			// The reason goes to the log and not to the response. A token is opaque,
			// so there is nothing in it for a client to repair; the only useful answer
			// is to drop it and start again, which is what the message says.
			$this->logger->debug(
				'Rejected a feed continuation token: {reason}',
				[
					'reason' => $e->getMessage(),
					'sources' => implode( '|', $sources ),
				]
			);

			throw new LocalizedHttpException(
				new MessageValue( 'personal-dashboard-rest-feed-bad-continue' ),
				400
			);
		}
	}

	/**
	 * Describe the response, drawing the item shapes from the sources.
	 *
	 * This is read once, when the API specification is generated, and never
	 * during a request — core calls it on a handler built without validated
	 * parameters. So it describes every registered source rather than the ones
	 * a caller asked for, which is also the only way a source contributed by
	 * another extension appears in the documentation at all.
	 *
	 * @inheritDoc
	 */
	protected function getResponseBodySchema( string $method ): ?array {
		$schemas = [];
		foreach ( $this->feedSourceFactory->getSourceNames() as $name ) {
			$source = $this->feedSourceFactory->getSource( $name );
			if ( $source ) {
				$schema = $source->getItemSchema();
				// Several sources usually share one item shape — the three
				// recentchanges-backed ones do — and repeating it would document
				// the same object three times.
				$schemas[ md5( (string)json_encode( $schema ) ) ] = $schema;
			}
		}
		$schemas = array_values( $schemas );

		return [
			'type' => 'object',
			'required' => [ 'items' ],
			'properties' => [
				'items' => [
					'type' => 'array',
					'x-i18n-description' => 'personal-dashboard-rest-schema-desc-feed-items',
					// An empty oneOf is invalid, so a wiki with no registered
					// source falls back to describing an unconstrained object.
					'items' => match ( count( $schemas ) ) {
						0 => [ 'type' => 'object' ],
						1 => $schemas[0],
						default => [ 'oneOf' => $schemas ],
					},
				],
				'continue' => [
					'type' => 'string',
					'x-i18n-description' => 'personal-dashboard-rest-schema-desc-feed-continue',
				],
			],
		];
	}

	/** @inheritDoc */
	public function getParamSettings(): array {
		return [
			'sources' => [
				self::PARAM_SOURCE => 'query',
				// The registered names, so an unknown one is a 400 from the
				// validator before this handler runs.
				ParamValidator::PARAM_TYPE => $this->feedSourceFactory->getSourceNames(),
				ParamValidator::PARAM_REQUIRED => false,
				// Omitting it asks for the widest, least viewer-specific feed
				// rather than nothing. The default is split like any supplied
				// value, so it arrives as an array.
				ParamValidator::PARAM_DEFAULT => self::DEFAULT_SOURCE,
				// Splits the value on "|", the separator the Action API uses.
				ParamValidator::PARAM_ISMULTI => true,
				self::PARAM_DESCRIPTION =>
					new MessageValue( 'personal-dashboard-rest-param-desc-feed-sources' ),
				self::PARAM_EXAMPLE => 'watchlist|recentchanges',
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => self::DEFAULT_LIMIT,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => self::MAX_LIMIT,
				self::PARAM_DESCRIPTION =>
					new MessageValue( 'personal-dashboard-rest-param-desc-feed-limit' ),
				self::PARAM_EXAMPLE => self::DEFAULT_LIMIT,
			],
			'continue' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION =>
					new MessageValue( 'personal-dashboard-rest-param-desc-feed-continue' ),
			],
		];
	}
}
