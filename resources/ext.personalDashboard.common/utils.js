// Gets up to n = limit items from an array
const getRandomItems = ( array, limit ) => {
	if ( array.length <= limit ) {
		mw.log.warn( `unable to randomly sample array: only ${ array.length } found` );
		return array;
	}
	const randomItems = [];
	while ( randomItems.length < limit ) {
		const randomIndex = Math.floor(
			Math.random() * array.length
		);
		const randomItem = array[ randomIndex ];
		if ( !randomItems.includes( randomItem ) ) {
			randomItems.push( array[ randomIndex ] );
		}
	}
	return randomItems;
};

// Gets error or warning messages from api response
const parseApiStatus = ( data ) => {
	const messages = [];
	for ( const index in data ) {
		const dataObj = data[ index ];
		// use the most specific message available
		const msg = dataObj.text || dataObj[ '*' ] || dataObj.code;
		messages.push( msg );
	}
	return messages;
};

// Consolidates errors from API response body and throws them in one error
const handleApiErrors = ( code, data ) => {
	if ( data === undefined ) {
		throw new Error( code );
	}
	if ( data.errors ) {
		const errors = parseApiStatus( data.errors );
		if ( errors.length > 0 ) {
			throw new Error( errors.join( '\n' ) );
		}
	}
};

module.exports = { getRandomItems, handleApiErrors, parseApiStatus };
