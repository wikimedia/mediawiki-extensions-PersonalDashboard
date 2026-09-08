<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\PersonalDashboard\Feed;

use InvalidArgumentException;

/**
 * The `continue` token: where each source resumes on the next page.
 *
 * A source name is present only while that source may still have something.
 * Its value is the cursor of the last item the merge took from it, or null when
 * it has items but contributed none to the page just served.
 *
 * The token also names everything the walk has served so far, so the next merge
 * can move a source past a copy of an item another source already showed. Those
 * names are hashes, packed four bytes each, because nothing reads a key back out
 * and the token has a size limit.
 *
 * That limit is what bounds the memory. When the token would outgrow
 * MAX_TOKEN_BYTES the oldest hashes fall off the front, so a very long walk
 * degrades to a sliding window rather than failing. At the default limit of ten
 * items a page, about 25 pages fit before that starts.
 *
 * The state stays in the token rather than in a session or a cache on purpose. A
 * GET that writes is the wrong shape for this: the main stash asks callers to
 * avoid non-POST writes and to tolerate eviction, a WANObjectCache write never
 * leaves its own datacentre, and a session write does nothing at all unless the
 * session is already persistent. Two dashboard tabs are also two walks for one
 * viewer, and a per-viewer seen-set would have them eat each other's items.
 * Core keeps paging state in the request too: PageHistoryHandler takes
 * older_than and newer_than as query parameters.
 *
 * The token is opaque to clients but it is **not** a capability. It carries
 * timestamps, row ids and hashes of things the viewer was just shown, and the
 * endpoint re-derives the authority from the session on every request, so it
 * needs no signing. It is validated rather than trusted: a token minted for one
 * set of sources cannot seed the cursors of another.
 */
readonly class FeedContinuation {

	private const int VERSION = 2;

	/**
	 * Guards against a client posting a huge string for us to base64-decode, and
	 * caps what encode() will mint, so a token we make always decodes.
	 */
	private const int MAX_TOKEN_BYTES = 2048;

	/** Length of one hashed dedup key, as FeedMerger writes it. */
	private const int HASH_CHARS = 8;

	/**
	 * @param array<string,?string> $cursors Source name to its resume point.
	 *   Only sources that may have more appear. Must not be empty; a token with
	 *   nothing live is a token that should never have been made.
	 * @param string[] $served Hashed dedup keys of everything served so far,
	 *   oldest first, from FeedMergeResult::$servedKeys. encode() drops from the
	 *   front of this if the token will not otherwise fit.
	 */
	public function __construct(
		public array $cursors,
		public array $served = [],
	) {
	}

	/**
	 * Identify a set of source names, order-insensitively.
	 *
	 * The token records this so a token made for `watchlist|recentchanges`
	 * cannot be replayed against `recentchanges|recentlyedited`, where its
	 * cursors would mean something else.
	 *
	 * @param string[] $sources
	 * @return string
	 */
	private static function fingerprint( array $sources ): string {
		$sorted = array_unique( $sources );
		sort( $sorted );

		return substr( hash( 'sha256', implode( '|', $sorted ) ), 0, 16 );
	}

	/**
	 * @param string[] $sources The source names this token belongs to.
	 * @return string
	 */
	public function encode( array $sources ): string {
		$served = $this->served;

		while ( true ) {
			$token = self::build( self::fingerprint( $sources ), $this->cursors, $served );
			$over = strlen( $token ) - self::MAX_TOKEN_BYTES;
			if ( $over <= 0 || !$served ) {
				return $token;
			}

			// Forget the oldest first: the newest page is the one a source is
			// most likely to still be holding a copy of. A four-byte hash costs
			// about seven bytes of token, having gone through base64 twice, so
			// dividing by eight always asks for fewer keys than it takes. That
			// is deliberate — the loop can then go round again, where over-
			// dropping would silently shorten the memory.
			$served = array_slice( $served, (int)ceil( $over / 8 ) );
		}
	}

	/**
	 * Encode one payload.
	 *
	 * @param string $fingerprint
	 * @param array<string,?string> $cursors
	 * @param string[] $served
	 * @return string
	 */
	private static function build( string $fingerprint, array $cursors, array $served ): string {
		// Packed, not a list of hex strings: four raw bytes a key rather than
		// ten with the quotes and the comma, which is most of what decides how
		// many pages the token can remember.
		$packed = '';
		foreach ( $served as $hash ) {
			$packed .= hex2bin( $hash );
		}

		$json = json_encode( [
			'v' => self::VERSION,
			'src' => $fingerprint,
			'c' => $cursors,
			'k' => base64_encode( $packed ),
		] );

		// base64url: a token rides in a query string, so + / and = are trouble.
		return rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	}

	/**
	 * Read a token back.
	 *
	 * @param string $token
	 * @param string[] $sources The source names of the request carrying it.
	 * @return self
	 * @throws InvalidArgumentException If the token is unusable for $sources.
	 *   The caller turns this into a 400; it never reaches the client as is.
	 */
	public static function decode( string $token, array $sources ): self {
		if ( $token === '' || strlen( $token ) > self::MAX_TOKEN_BYTES ) {
			throw new InvalidArgumentException( 'continue token is empty or too long' );
		}

		$json = base64_decode( strtr( $token, '-_', '+/' ), true );
		if ( $json === false ) {
			throw new InvalidArgumentException( 'continue token is not base64url' );
		}

		$payload = json_decode( $json, true );
		if ( !is_array( $payload ) ) {
			throw new InvalidArgumentException( 'continue token is not a JSON object' );
		}

		if ( ( $payload['v'] ?? null ) !== self::VERSION ) {
			throw new InvalidArgumentException( 'continue token is of another version' );
		}

		if ( ( $payload['src'] ?? null ) !== self::fingerprint( $sources ) ) {
			throw new InvalidArgumentException( 'continue token belongs to other sources' );
		}

		$cursors = $payload['c'] ?? null;
		if ( !is_array( $cursors ) || !$cursors ) {
			throw new InvalidArgumentException( 'continue token names no live source' );
		}

		foreach ( $cursors as $name => $cursor ) {
			if ( !in_array( $name, $sources, true ) ) {
				throw new InvalidArgumentException( "continue token names unknown source '$name'" );
			}
			if ( $cursor !== null && !is_string( $cursor ) ) {
				throw new InvalidArgumentException( "continue token has a bad cursor for '$name'" );
			}
		}

		return new self( $cursors, self::unpack( $payload['k'] ?? '' ) );
	}

	/**
	 * Read the served keys back out of a payload.
	 *
	 * @param mixed $packed
	 * @return string[]
	 * @throws InvalidArgumentException If it is not a run of whole hashes.
	 */
	private static function unpack( $packed ): array {
		if ( !is_string( $packed ) ) {
			throw new InvalidArgumentException( 'continue token has a bad served list' );
		}

		if ( $packed === '' ) {
			return [];
		}

		$raw = base64_decode( $packed, true );
		if ( $raw === false ) {
			throw new InvalidArgumentException( 'continue token has an undecodable served list' );
		}

		$hex = bin2hex( $raw );
		if ( strlen( $hex ) % self::HASH_CHARS !== 0 ) {
			throw new InvalidArgumentException( 'continue token has a truncated served key' );
		}

		return str_split( $hex, self::HASH_CHARS );
	}
}
