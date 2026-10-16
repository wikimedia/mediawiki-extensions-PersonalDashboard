<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Tests\Unit\Routing;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\PersonalDashboard\Routing\SlugAliasLookup;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\PersonalDashboard\Routing\SlugAliasLookup
 */
class SlugAliasLookupTest extends MediaWikiUnitTestCase {

	private const FIXTURES = [
		'OneSlugs' => __DIR__ . '/fixtures/slugs-one.php',
		'TwoSlugs' => __DIR__ . '/fixtures/slugs-two.php',
	];

	/**
	 * @param string[] $keys ExtensionMessagesFiles keys the attribute names
	 * @param string $contentLanguage Content language code
	 * @param string[] $fallbacks Its fallback chain
	 * @param ?LoggerInterface $logger Logger to assert against, or null
	 * @param ?array $files ExtensionMessagesFiles, defaulting to the fixtures
	 * @return SlugAliasLookup
	 */
	private function newLookup(
		array $keys,
		string $contentLanguage = 'en',
		array $fallbacks = [],
		?LoggerInterface $logger = null,
		?array $files = null
	): SlugAliasLookup {
		$registry = $this->createMock( ExtensionRegistry::class );
		$registry->method( 'getAttribute' )
			->with( 'PersonalDashboardSlugAliasFiles' )
			->willReturn( $keys );

		$language = $this->createMock( Language::class );
		$language->method( 'getCode' )->willReturn( $contentLanguage );

		$languageFallback = $this->createMock( LanguageFallback::class );
		$languageFallback->method( 'getAll' )->willReturn( $fallbacks );

		return new SlugAliasLookup(
			$registry,
			new HashConfig( [
				MainConfigNames::ExtensionMessagesFiles => $files ?? self::FIXTURES,
			] ),
			$language,
			$languageFallback,
			$logger ?? new NullLogger()
		);
	}

	public function testTheContentLanguagesSlugsComeFirst() {
		$aliases = $this->newLookup( [ 'OneSlugs' ], 'de', [ 'en' ] )->getAliases();

		// The preferred slug is index 0: the nearest language's first.
		$this->assertSame( 'wirkung', $aliases['impact'][0] );
		// Every slug down the chain is kept, English included.
		$this->assertSame(
			[ 'wirkung', 'auswirkung', 'impact', 'my-impact' ], $aliases['impact'] );
	}

	public function testTheFallbackChainSuppliesWhatTheLanguageDoesNot() {
		$aliases = $this->newLookup(
			[ 'OneSlugs' ], 'de-formal', [ 'de', 'en' ] )->getAliases();

		// de-formal declares only review, so impact comes from de.
		$this->assertSame( 'pruefung', $aliases['review'][0] );
		$this->assertSame( 'wirkung', $aliases['impact'][0] );
	}

	public function testASlugWithNoAliasAtAllIsAbsent() {
		$aliases = $this->newLookup( [ 'OneSlugs' ], 'en' )->getAliases();

		$this->assertArrayNotHasKey( 'discussions', $aliases );
	}

	/**
	 * The attribute merges across extensions, so the files do too. Declaration
	 * order decides a clash, the same way the module group registry does.
	 */
	public function testFilesMergePerLanguageWithTheFirstDeclarationWinning() {
		$aliases = $this->newLookup(
			[ 'OneSlugs', 'TwoSlugs' ], 'de', [ 'en' ] )->getAliases();

		$this->assertSame( 'diskussionen', $aliases['discussions'][0] );
		$this->assertSame( 'wirkung', $aliases['impact'][0] );
		$this->assertNotContains( 'ueberschrieben', $aliases['impact'] );
	}

	/**
	 * The extension naming the file is disabled, or the key is a typo. Lose its
	 * slugs rather than the page: a canonical slug resolves without them.
	 */
	public function testAnUnregisteredKeyIsLoggedAndSkipped() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );

		$aliases = $this->newLookup(
			[ 'OneSlugs', 'AbsentSlugs' ], 'en', [], $logger )->getAliases();

		$this->assertSame( 'impact', $aliases['impact'][0] );
	}

	public function testAKeyNamingAFileThatIsNotThereIsLoggedAndSkipped() {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );

		$aliases = $this->newLookup(
			[ 'GoneSlugs' ],
			'en',
			[],
			$logger,
			[ 'GoneSlugs' => __DIR__ . '/fixtures/nothing-here.php' ]
		)->getAliases();

		$this->assertSame( [], $aliases );
	}

	public function testNoRegisteredFilesMeansNoAliases() {
		$this->assertSame( [], $this->newLookup( [] )->getAliases() );
	}

	public function testASlugThatCannotBeAUrlSegmentIsDropped() {
		$aliases = $this->newLookup( [ 'TwoSlugs' ], 'de' )->getAliases();

		$this->assertSame( [ 'diskussionen' ], $aliases['discussions'] );
	}
}
