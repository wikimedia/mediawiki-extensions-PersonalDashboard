<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Rest;

use MediaWiki\Extension\PersonalDashboard\Feed\FeedRequest;
use MediaWiki\Extension\PersonalDashboard\Feed\FeedSourceResult;
use MediaWiki\Extension\PersonalDashboard\Feed\IFeedItem;
use MediaWiki\Extension\PersonalDashboard\Feed\IFeedSource;
use MediaWiki\Extension\PersonalDashboard\Feed\PersonalDashboardFeedSourceFactory;
use MediaWiki\Extension\PersonalDashboard\Rest\FeedHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * The handler is tested against stub sources, not the real queries. What the
 * three real sources select is covered by their own tests; what matters here is
 * everything around them — who is turned away, how the limit is spent, and how
 * the continuation token is made and read back.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Rest\FeedHandler
 * @group PersonalDashboard
 */
class FeedHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/** Set by a test that wants to watch what the handler logs. */
	private ?LoggerInterface $logger = null;

	private function item( string $source, string $key, string $timestamp ): IFeedItem {
		return new class( $source, $key, $timestamp ) implements IFeedItem {
			public function __construct(
				private readonly string $source,
				private readonly string $key,
				private readonly string $timestamp,
			) {
			}

			public function getId(): string {
				return $this->source . '-' . $this->key;
			}

			public function getTimestamp(): string {
				return $this->timestamp;
			}

			public function getCursor(): string {
				return 'cursor:' . $this->key;
			}

			public function getDedupKey(): ?string {
				return $this->key;
			}

			public function toArray(): array {
				return [ 'id' => $this->getId(), 'feedorigin' => $this->source, 'key' => $this->key ];
			}
		};
	}

	/**
	 * A source that hands back what the test told it to, and remembers what it
	 * was asked for.
	 *
	 * @param IFeedItem[] $items
	 * @param bool $mayHaveMore
	 * @return IFeedSource
	 */
	private function source(
		array $items,
		bool $mayHaveMore = false,
		?array $itemSchema = null
	): IFeedSource {
		return new class( $items, $mayHaveMore, $itemSchema ) implements IFeedSource {
			public ?FeedRequest $seen = null;
			public string $name = '';

			public function __construct(
				private readonly array $items,
				private readonly bool $mayHaveMore,
				private readonly ?array $itemSchema,
			) {
			}

			public function getItems( FeedRequest $request ): FeedSourceResult {
				$this->seen = $request;
				return new FeedSourceResult( $this->items, $this->mayHaveMore );
			}

			public function setName( string $name ): void {
				$this->name = $name;
			}

			public function getItemSchema(): array {
				return $this->itemSchema ?? [
					'type' => 'object',
					'properties' => [ 'key' => [ 'type' => 'string' ] ],
				];
			}
		};
	}

	/**
	 * Register the given sources and build a handler over them.
	 *
	 * @param array<string,IFeedSource> $sources
	 * @return FeedHandler
	 */
	private function newHandler( array $sources ): FeedHandler {
		$specs = [];
		foreach ( $sources as $name => $source ) {
			$specs[$name] = [ 'factory' => static fn () => $source ];
		}

		// T413223 - $scope is unused, but needed for PHP 8.5's #[NoDiscard] on
		// setAttributeForTest()
		$scope = ExtensionRegistry::getInstance()
			->setAttributeForTest( 'PersonalDashboardFeedSources', $specs );

		return new FeedHandler(
			new PersonalDashboardFeedSourceFactory(
				ExtensionRegistry::getInstance(),
				$this->getServiceContainer()->getObjectFactory(),
				new NullLogger()
			),
			$this->logger ?? new NullLogger()
		);
	}

	private function request( string $sources, array $query = [] ): RequestData {
		return new RequestData( [
			'queryParams' => [ 'sources' => $sources ] + $query,
		] );
	}

	/**
	 * A named viewer. The sources are stubs, so no test here needs a real
	 * account in the database — only an authority that answers isNamed().
	 *
	 * @return Authority
	 */
	private function viewer(): Authority {
		return $this->mockRegisteredUltimateAuthority();
	}

	private function executeAndCatch(
		FeedHandler $handler, RequestData $request, ?Authority $authority
	): HttpException {
		try {
			$this->executeHandler( $handler, $request, [], [], [], [], $authority );
		} catch ( HttpException $e ) {
			return $e;
		}

		$this->fail( 'expected the request to be rejected' );
	}

	public function testMergesTheNamedSourcesNewestFirst(): void {
		$handler = $this->newHandler( [
			'alpha' => $this->source( [
				$this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ),
				$this->item( 'alpha', 'a2', '2026-03-10T09:00:00Z' ),
			] ),
			'beta' => $this->source( [
				$this->item( 'beta', 'b1', '2026-03-10T11:00:00Z' ),
			] ),
		] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			$this->request( 'alpha|beta', [ 'limit' => '10' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame(
			[ 'a1', 'b1', 'a2' ],
			array_column( $data['items'], 'key' )
		);
	}

	public function testTurnsAwayAnAnonymousViewer(): void {
		// The default authority in executeHandler() is anonymous, so passing
		// none exercises exactly the case we care about.
		$handler = $this->newHandler( [ 'alpha' => $this->source( [] ) ] );

		$exception = $this->executeAndCatch( $handler, $this->request( 'alpha' ), null );

		$this->assertSame( 401, $exception->getCode() );
	}

	public function testTurnsAwayATemporaryAccount(): void {
		// isNamed(), not isRegistered(): a temp account has no watchlist and no
		// edit history to build a feed from.
		$handler = $this->newHandler( [ 'alpha' => $this->source( [] ) ] );

		$exception = $this->executeAndCatch(
			$handler,
			$this->request( 'alpha' ),
			$this->mockTempUltimateAuthority()
		);

		$this->assertSame( 401, $exception->getCode() );
	}

	public function testRejectsAnUnregisteredSourceName(): void {
		// The registered names are the parameter's type, so the validator turns
		// a typo away before the handler runs.
		$handler = $this->newHandler( [ 'alpha' => $this->source( [] ) ] );

		$exception = $this->executeAndCatch(
			$handler,
			$this->request( 'nosuchsource' ),
			$this->viewer()
		);

		$this->assertSame( 400, $exception->getCode() );
	}

	public function testRejectsALimitAboveTheMaximum(): void {
		$handler = $this->newHandler( [ 'alpha' => $this->source( [] ) ] );

		$exception = $this->executeAndCatch(
			$handler,
			$this->request( 'alpha', [ 'limit' => '5000' ] ),
			$this->viewer()
		);

		$this->assertSame( 400, $exception->getCode() );
	}

	public function testAsksEverySourceForTheWholeLimit(): void {
		// Not its share of it. One source has to be able to fill the page when
		// the others come up dry.
		$alpha = $this->source( [ $this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ) ] );
		$beta = $this->source( [] );

		$this->executeHandlerAndGetBodyData(
			$this->newHandler( [ 'alpha' => $alpha, 'beta' => $beta ] ),
			$this->request( 'alpha|beta', [ 'limit' => '7' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame( 7, $alpha->seen->limit );
		$this->assertSame( 7, $beta->seen->limit );
	}

	public function testOffersNoContinuationWhenEverySourceIsSpent(): void {
		$handler = $this->newHandler( [
			'alpha' => $this->source( [ $this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ) ], false ),
		] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			$this->request( 'alpha', [ 'limit' => '10' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertArrayNotHasKey( 'continue', $data );
	}

	public function testOffersAContinuationWhileASourceMayHaveMore(): void {
		$handler = $this->newHandler( [
			'alpha' => $this->source( [
				$this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ),
				$this->item( 'alpha', 'a2', '2026-03-10T11:00:00Z' ),
			] ),
		] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			$this->request( 'alpha', [ 'limit' => '1' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertArrayHasKey( 'continue', $data );
		$this->assertNotSame( '', $data['continue'] );
	}

	public function testOffersNoContinuationWhenNothingMoved(): void {
		// FeedSourceResult tells a source that cannot tell cheaply to report that
		// more may follow. A source that does so while returning nothing leaves no
		// cursor to advance, so a token here would repeat the request that made it
		// and the client would page forever on the same empty answer.
		$handler = $this->newHandler( [
			'alpha' => $this->source( [], true ),
		] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			$this->request( 'alpha', [ 'limit' => '10' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame( [], $data['items'] );
		$this->assertArrayNotHasKey( 'continue', $data );
	}

	public function testKeepsTheContinuationWhenAnotherSourceMoved(): void {
		// Only a page that moved nothing at all ends the feed. An idle source next
		// to a working one must not cut the paging short.
		$handler = $this->newHandler( [
			'alpha' => $this->source( [], true ),
			'beta' => $this->source( [
				$this->item( 'beta', 'b1', '2026-03-10T12:00:00Z' ),
				$this->item( 'beta', 'b2', '2026-03-10T11:00:00Z' ),
			] ),
		] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			$this->request( 'alpha|beta', [ 'limit' => '1' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame( [ 'b1' ], array_column( $data['items'], 'key' ) );
		$this->assertArrayHasKey( 'continue', $data );
	}

	public function testTheContinuationResumesEachSourceFromItsCursor(): void {
		$first = $this->newHandler( [
			'alpha' => $this->source( [
				$this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ),
				$this->item( 'alpha', 'a2', '2026-03-10T11:00:00Z' ),
			] ),
		] );

		$page = $this->executeHandlerAndGetBodyData(
			$first,
			$this->request( 'alpha', [ 'limit' => '1' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$alpha = $this->source( [ $this->item( 'alpha', 'a2', '2026-03-10T11:00:00Z' ) ] );
		$this->executeHandlerAndGetBodyData(
			$this->newHandler( [ 'alpha' => $alpha ] ),
			$this->request( 'alpha', [ 'limit' => '1', 'continue' => $page['continue'] ] ),
			[], [], [], [],
			$this->viewer()
		);

		// The cursor of the last item the merge used, not of the last item the
		// source offered.
		$this->assertSame( 'cursor:a1', $alpha->seen->cursor );
	}

	public function testAnItemOneSourceStillHoldsIsNotServedAgain(): void {
		// Both sources hold X. The merge takes beta's copy for page 1 and never
		// reaches alpha's, so alpha resumes standing in front of it.
		$page1 = $this->executeHandlerAndGetBodyData(
			$this->newHandler( [
				'alpha' => $this->source( [
					$this->item( 'alpha', 'A', '2026-03-10T12:00:00Z' ),
					$this->item( 'alpha', 'X', '2026-03-10T10:00:00Z' ),
				], true ),
				'beta' => $this->source( [
					$this->item( 'beta', 'X', '2026-03-10T10:00:00Z' ),
					$this->item( 'beta', 'B', '2026-03-10T09:00:00Z' ),
				], true ),
			] ),
			$this->request( 'alpha|beta', [ 'limit' => '2' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame( [ 'A', 'X' ], array_column( $page1['items'], 'key' ) );

		// What each source has left once it resumes from the cursor page 1 gave it.
		$page2 = $this->executeHandlerAndGetBodyData(
			$this->newHandler( [
				'alpha' => $this->source( [ $this->item( 'alpha', 'X', '2026-03-10T10:00:00Z' ) ] ),
				'beta' => $this->source( [ $this->item( 'beta', 'B', '2026-03-10T09:00:00Z' ) ] ),
			] ),
			$this->request( 'alpha|beta', [ 'limit' => '2', 'continue' => $page1['continue'] ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame(
			[ 'B' ],
			array_column( $page2['items'], 'key' ),
			'X went out on page 1, so the copy alpha still holds must not go out again'
		);
	}

	public function testAnItemStaysDedupedMoreThanOnePageLater(): void {
		// Remembering only the page before is not enough. alpha serves X and is
		// spent; beta then serves B, which says nothing about X; only on the third
		// page does beta reach its own copy of X. By then the page that served X is
		// two back.
		$walk = function ( array $sources, ?string $continue ) {
			return $this->executeHandlerAndGetBodyData(
				$this->newHandler( $sources ),
				$this->request( 'alpha|beta', array_filter( [
					'limit' => '1',
					'continue' => $continue,
				] ) ),
				[], [], [], [],
				$this->viewer()
			);
		};
		$betaHolds = fn () => $this->source( [
			$this->item( 'beta', 'B', '2026-03-10T12:00:00Z' ),
			$this->item( 'beta', 'X', '2026-03-10T10:00:00Z' ),
		] );

		$page1 = $walk( [
			'alpha' => $this->source( [ $this->item( 'alpha', 'X', '2026-03-10T10:00:00Z' ) ] ),
			'beta' => $betaHolds(),
		], null );
		$this->assertSame( [ 'X' ], array_column( $page1['items'], 'key' ) );

		// alpha is spent and has left the token. beta has not moved yet, so it
		// still offers the same two items.
		$page2 = $walk(
			[ 'alpha' => $this->source( [] ), 'beta' => $betaHolds() ], $page1['continue']
		);
		$this->assertSame( [ 'B' ], array_column( $page2['items'], 'key' ) );

		$page3 = $walk( [
			'alpha' => $this->source( [] ),
			'beta' => $this->source( [ $this->item( 'beta', 'X', '2026-03-10T10:00:00Z' ) ] ),
		], $page2['continue'] );

		$this->assertSame(
			[],
			array_column( $page3['items'], 'key' ),
			'the token remembers the whole walk, not just the page before it'
		);
	}

	public function testRejectsAContinuationMadeForOtherSources(): void {
		// Its cursors mean something else against a different set of sources, so
		// honouring it would silently skip or repeat items.
		$sources = [
			'alpha' => $this->source( [ $this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ) ] ),
			'beta' => $this->source( [ $this->item( 'beta', 'b1', '2026-03-10T11:00:00Z' ) ] ),
		];

		$page = $this->executeHandlerAndGetBodyData(
			$this->newHandler( $sources ),
			$this->request( 'alpha|beta', [ 'limit' => '1' ] ),
			[], [], [], [],
			$this->viewer()
		);

		// A handler is initialised once, so the second request needs its own.
		$exception = $this->executeAndCatch(
			$this->newHandler( $sources ),
			$this->request( 'alpha', [ 'continue' => $page['continue'] ] ),
			$this->viewer()
		);

		$this->assertSame( 400, $exception->getCode() );
	}

	public function testRejectsAGarbledContinuation(): void {
		$handler = $this->newHandler( [ 'alpha' => $this->source( [] ) ] );

		$exception = $this->executeAndCatch(
			$handler,
			$this->request( 'alpha', [ 'continue' => 'not-a-real-token' ] ),
			$this->viewer()
		);

		$this->assertSame( 400, $exception->getCode() );
	}

	public function testWhyATokenWasRejectedReachesTheLog(): void {
		// The response says only that the token is unusable, which is all a
		// client can act on. The specific reason is the one thing about the
		// failure that cannot be recovered from outside, so it has to be logged
		// or it is gone.
		$logged = [];
		$this->logger = new class( $logged ) extends AbstractLogger {
			public function __construct( private array &$logged ) {
			}

			public function log( $level, $message, array $context = [] ): void {
				$this->logged[] = [ $level, $message, $context ];
			}
		};

		$this->executeAndCatch(
			$this->newHandler( [ 'alpha' => $this->source( [] ) ] ),
			$this->request( 'alpha', [ 'continue' => 'not-a-real-token' ] ),
			$this->viewer()
		);

		$this->assertCount( 1, $logged );
		[ $level, $message, $context ] = $logged[0];
		$this->assertSame( LogLevel::DEBUG, $level, 'a bad token is a client bug, not an incident' );
		$this->assertStringContainsString( '{reason}', $message );
		// Which reason is itself the point: "not-a-real-token" is made of legal
		// base64url characters, so it decodes cleanly and only fails at the JSON
		// step. That distinction is invisible from the 400 alone.
		$this->assertStringContainsString( 'not a JSON object', $context['reason'] );
		$this->assertSame( 'alpha', $context['sources'] );
	}

	public function testNamingNoSourcesFallsBackToRecentChanges(): void {
		$recentChanges = $this->source( [ $this->item( 'recentchanges', 'r1', '2026-03-10T12:00:00Z' ) ] );

		$data = $this->executeHandlerAndGetBodyData(
			$this->newHandler( [
				'recentchanges' => $recentChanges,
				'watchlist' => $this->source( [ $this->item( 'watchlist', 'w1', '2026-03-10T11:00:00Z' ) ] ),
			] ),
			new RequestData( [ 'queryParams' => [] ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame( [ 'r1' ], array_column( $data['items'], 'key' ) );
		$this->assertNotNull( $recentChanges->seen, 'the default source should have been asked' );
	}

	public function testTheResponseSchemaDescribesEveryRegisteredSource(): void {
		// It is built for the API specification, which has no request, so it
		// covers what is registered rather than what someone asked for.
		$handler = $this->newHandler( [
			'alpha' => $this->source( [], false, [ 'type' => 'object', 'title' => 'Alpha' ] ),
			'beta' => $this->source( [], false, [ 'type' => 'object', 'title' => 'Beta' ] ),
		] );

		$schema = TestingAccessWrapper::newFromObject( $handler )->getResponseBodySchema( 'get' );

		$this->assertSame( [ 'items' ], $schema['required'] );
		$this->assertSame( 'array', $schema['properties']['items']['type'] );
		$this->assertSame(
			[ [ 'type' => 'object', 'title' => 'Alpha' ], [ 'type' => 'object', 'title' => 'Beta' ] ],
			$schema['properties']['items']['items']['oneOf']
		);
		$this->assertSame( 'string', $schema['properties']['continue']['type'] );
	}

	public function testSourcesSharingAnItemShapeAreDescribedOnce(): void {
		// The three recentchanges-backed sources all emit the same item, and a
		// oneOf listing it three times would document nothing extra.
		$shape = [ 'type' => 'object', 'title' => 'Same' ];
		$handler = $this->newHandler( [
			'alpha' => $this->source( [], false, $shape ),
			'beta' => $this->source( [], false, $shape ),
		] );

		$schema = TestingAccessWrapper::newFromObject( $handler )->getResponseBodySchema( 'get' );

		$this->assertSame( $shape, $schema['properties']['items']['items'] );
		$this->assertArrayNotHasKey( 'oneOf', $schema['properties']['items']['items'] );
	}

	public function testTheSchemaSurvivesAWikiWithNoRegisteredSources(): void {
		// An empty oneOf is not valid JSON Schema, so the envelope has to fall
		// back to an unconstrained object rather than emit one.
		$handler = $this->newHandler( [] );

		$schema = TestingAccessWrapper::newFromObject( $handler )->getResponseBodySchema( 'get' );

		$this->assertSame( [ 'type' => 'object' ], $schema['properties']['items']['items'] );
	}

	public function testASourceNamedTwiceGetsOneShareOfTheFeed(): void {
		$alpha = $this->source( [
			$this->item( 'alpha', 'a1', '2026-03-10T12:00:00Z' ),
			$this->item( 'alpha', 'a2', '2026-03-10T11:00:00Z' ),
		] );

		$data = $this->executeHandlerAndGetBodyData(
			$this->newHandler( [ 'alpha' => $alpha ] ),
			$this->request( 'alpha|alpha', [ 'limit' => '10' ] ),
			[], [], [], [],
			$this->viewer()
		);

		$this->assertSame( [ 'a1', 'a2' ], array_column( $data['items'], 'key' ) );
	}
}
