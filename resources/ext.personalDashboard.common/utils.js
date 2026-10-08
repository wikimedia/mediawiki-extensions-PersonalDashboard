const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );

// Strips HTML markup down to its plain-text content, for feed data (e.g. a
// parsed comment) that arrives as HTML but renders as text.
function stripMarkup( html ) {
	if ( !html ) {
		return '';
	}
	return new DOMParser().parseFromString( html, 'text/html' ).body.textContent;
}

// Formats a raw ISO timestamp (a feed item's `timestamp`) the same way across
// every ListCard.
function formatTimestamp( rawTimestamp ) {
	return formatRelativeTimeOrDate( new Date( Date.parse( rawTimestamp ) ) );
}

module.exports = { formatTimestamp, stripMarkup };
