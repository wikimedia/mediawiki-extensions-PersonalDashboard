<?php
namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit;

use MediaWiki\Extension\PersonalDashboard\WikiConfigException;
use MediaWikiUnitTestCase;
use Wikimedia\NormalizedException\NormalizedException;

/**
 * @coversDefaultClass \MediaWiki\Extension\PersonalDashboard\WikiConfigException
 */
class WikiConfigExceptionTest extends MediaWikiUnitTestCase {
	/**
	 * @covers ::__construct
	 */
	public function testConstruction() {
		$exception = new WikiConfigException( 'Foo' );
		$this->assertInstanceOf( WikiConfigException::class, $exception );
		$this->assertInstanceOf( NormalizedException::class, $exception );
	}
}
