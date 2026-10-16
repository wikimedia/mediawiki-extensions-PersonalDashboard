<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Routing;

use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;

/**
 * The localized slugs of the module routes, read from the slug alias files
 * that this extension and any integrating extension register.
 *
 * A file declares `$personalDashboardSlugAliases[<language>][<canonical slug>]`
 * as a list of slugs, in the shape `$specialPageAliases` uses, and sits beside
 * that global in an extension's own alias file. It is named by an entry under
 * the `PersonalDashboard.SlugAliasFiles` attribute, which holds keys into
 * `$wgExtensionMessagesFiles` rather than paths: extension.json attributes are
 * merged verbatim, while ExtensionMessagesFiles values are rewritten to
 * absolute paths, so the indirection is what resolves the file for an
 * extension in any directory.
 *
 * LocalisationCache reads those same files for their special page aliases and
 * ignores this global, because readPHPFile() keeps only the keys it knows and
 * this is not one. So we read them here instead. See ../../docs/routing.md.
 */
class SlugAliasLookup {

	/** Memo of getAliases(): canonical slug => its slugs, most preferred first. */
	private ?array $aliases = null;

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly Config $mainConfig,
		private readonly Language $contentLanguage,
		private readonly LanguageFallback $languageFallback,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Every declared slug for every canonical slug, in the content language.
	 *
	 * Content language and not the viewer's: a route is part of a page URL, so
	 * it must read the same for every viewer of the wiki, must survive being
	 * pasted into a wiki page, and must not split one module's analytics across
	 * a slug per language. This is how special page aliases already behave.
	 *
	 * The list walks the content language's fallback chain, nearest first, so
	 * the first is the one to prefer when publishing while every slug down the
	 * chain still resolves. A viewer on `?uselang=` therefore keeps whatever
	 * link they already had. Whether the preferred slug is the one actually
	 * published is ModuleRouteMap's call, not ours: it alone knows what else
	 * in the group has claimed that route.
	 *
	 * @return array<string,string[]> Canonical slug => its slugs, preferred first
	 */
	public function getAliases(): array {
		if ( $this->aliases !== null ) {
			return $this->aliases;
		}

		$declared = $this->readFiles();
		$code = $this->contentLanguage->getCode();
		$chain = [ $code, ...$this->languageFallback->getAll( $code ) ];

		$aliases = [];

		foreach ( $chain as $language ) {
			foreach ( $declared[$language] ?? [] as $canonical => $slugs ) {
				foreach ( (array)$slugs as $slug ) {
					if ( is_string( $slug ) && trim( $slug ) !== '' ) {
						$aliases[$canonical][] = $slug;
					}
				}
			}
		}

		$this->aliases = $aliases;

		return $this->aliases;
	}

	/**
	 * The declarations of every registered slug alias file, merged.
	 *
	 * A file that two extensions both declare a language for merges per
	 * language, first declaration winning, the same way the module group
	 * registry merges.
	 *
	 * @return array<string,array<string,string[]>> Language => canonical => slugs
	 */
	private function readFiles(): array {
		$keys = $this->extensionRegistry->getAttribute( 'PersonalDashboardSlugAliasFiles' );
		$registered = $this->mainConfig->get( MainConfigNames::ExtensionMessagesFiles );
		$declared = [];

		foreach ( $keys as $key ) {
			$path = $registered[$key] ?? null;

			if ( !is_string( $path ) || !is_file( $path ) ) {
				// The extension naming the file is disabled, or the key is a
				// typo. Lose its localized slugs rather than the whole page:
				// every canonical slug still resolves without them.
				$this->logger->error(
					'Personal Dashboard: slug alias file {key} is not registered',
					[ 'key' => $key ]
				);
				continue;
			}

			foreach ( self::readFile( $path ) as $language => $slugs ) {
				$declared[$language] = ( $declared[$language] ?? [] ) + (array)$slugs;
			}
		}

		return $declared;
	}

	/**
	 * @param string $path Absolute path to a slug alias file
	 * @return array<string,array<string,string[]>> Language => canonical => slugs
	 */
	private static function readFile( string $path ): array {
		// Declared first so the file is read into this scope and a file that
		// sets nothing leaves an array behind rather than an undefined name.
		$personalDashboardSlugAliases = [];

		require $path;

		// The file is hand-written and sets this to whatever it likes, which
		// Phan cannot see through a require; a malformed one must not reach the
		// foreach above as a string.
		// @phan-suppress-next-line PhanRedundantCondition
		return is_array( $personalDashboardSlugAliases ) ? $personalDashboardSlugAliases : [];
	}
}
