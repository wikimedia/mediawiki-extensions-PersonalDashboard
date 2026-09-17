const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );

// Gets error or warning messages from api response
function parseApiStatus( data ) {
	const messages = [];
	for ( const index in data ) {
		const dataObj = data[ index ];
		// use the most specific message available
		const msg = dataObj.text || dataObj[ '*' ] || dataObj.code;
		messages.push( msg );
	}
	return messages;
}

// Consolidates errors from API response body and throws them in one error
function handleApiErrors( code, data ) {
	if ( data === undefined ) {
		throw new Error( code );
	}
	if ( data.errors ) {
		const errors = parseApiStatus( data.errors );
		if ( errors.length > 0 ) {
			throw new Error( errors.join( '\n' ) );
		}
	}
	throw new Error( code );
}

// Strips HTML markup down to its plain-text content, for feed data (e.g. a
// parsed comment or a thread title) that arrives as HTML but renders as text.
function stripMarkup( html ) {
	if ( !html ) {
		return '';
	}
	return new DOMParser().parseFromString( html, 'text/html' ).body.textContent;
}

// Formats a raw ISO timestamp (a feed item's `timestamp` or a discussion
// thread's `latestReply`) the same way across every ListCard.
function formatTimestamp( rawTimestamp ) {
	return formatRelativeTimeOrDate( new Date( Date.parse( rawTimestamp ) ) );
}

module.exports = { formatTimestamp, handleApiErrors, stripMarkup };
