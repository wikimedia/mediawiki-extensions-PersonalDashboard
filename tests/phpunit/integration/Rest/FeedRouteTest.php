<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Integration\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Tests\Rest\Handler\HandlerIntegrationTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * The route itself, through the real router.
 *
 * FeedHandlerTest drives the handler directly, which skips the two things only
 * routing does: decoding the path and registering the route at all. Both are
 * easy to break and invisible to a handler test.
 *
 * @covers \MediaWiki\Extension\PersonalDashboard\Rest\FeedHandler
 * @group PersonalDashboard
 * @group Database
 */
class FeedRouteTest extends MediaWikiIntegrationTestCase {

	use HandlerIntegrationTestTrait;

	private const string PATH = '/rest.php/personaldashboard/v0/feed';

	public function testTheRouteIsRegisteredAndAnswers(): void {
		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );

		$response = $this->execute( [
			'path' => self::PATH,
			'queryParams' => [ 'sources' => 'recentchanges', 'limit' => '3' ],
		] );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'application/json', $response->getHeaderLine( 'Content-Type' ) );

		$data = json_decode( (string)$response->getBody(), true );
		$this->assertIsArray( $data['items'] );

		// The response is per-viewer. Handler::applyCacheControl() marks it
		// private, but only when the session is persistent, which it is not
		// under test — so the strongest thing assertable here is that this
		// endpoint never declares itself publicly cacheable.
		$this->assertStringNotContainsString(
			'public',
			$response->getHeaderLine( 'Cache-Control' )
		);
	}

	public function testAPipeSeparatesTheSources(): void {
		// The same separator the Action API uses. Nothing else proves the value
		// is split rather than read as one unrecognised name.
		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );

		$response = $this->execute( [
			'path' => self::PATH,
			'queryParams' => [ 'sources' => 'recentchanges|watchlist', 'limit' => '3' ],
		] );

		$this->assertSame(
			200,
			$response->getStatusCode(),
			'both sources should have been recognised, not read as one unknown name'
		);
	}

	public function testNamingNoSourcesIsSuccessful(): void {
		// A bare /feed is a valid request, not a missing parameter.
		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );

		$response = $this->execute( [ 'path' => self::PATH ] );

		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testAnUnknownSourceIsRejectedByTheRoute(): void {
		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );

		$response = $this->execute( [
			'path' => self::PATH,
			'queryParams' => [ 'sources' => 'nosuchsource' ],
		] );

		$this->assertSame( 400, $response->getStatusCode() );
	}
}
