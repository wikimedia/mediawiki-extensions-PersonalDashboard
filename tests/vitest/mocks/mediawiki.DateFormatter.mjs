export function formatDate( date ) {
	const formattedDate = date.toLocaleDateString( 'en-us', { day: '2-digit', month: 'long', year: 'numeric' } );
	const splitDate = formattedDate.split( ' ' );
	return `${ splitDate[ 1 ] } ${ splitDate[ 0 ] } ${ splitDate[ 2 ] }`;
}

export function formatTime( date ) {
	return date.toLocaleTimeString( 'en-us', { hour: 'numeric', minute: 'numeric' } );
}

export function formatRelativeTimeOrDate( date ) {
	// Making "today's" date fixed because otherwise we will have flaky tests
	const dateNow = new Date( 2026, 1, 1 );
	if ( typeof Intl === 'undefined' || typeof Intl.RelativeTimeFormat !== 'function' ) {
		// Intl.RelativeTimeFormat() is not supported in Safari 11.1, iOS Safari 11.3-11.4
		return 'invalid date';
	}
	// from mediawiki/extensions/MobileFrontend/src/mobile.startup/time.js
	const seconds = ( date.getTime() - dateNow ) / 1000;
	const units = [ 'seconds', 'minutes', 'hours', 'days', 'months', 'years' ];
	const limits = [ 1, 60, 3600, 86400, 2592000, 31536000 ];
	let i = 0;
	while ( i < limits.length && Math.abs( seconds ) > limits[ i + 1 ] ) {
		++i;
	}
	const value = Math.round( seconds / limits[ i ] );
	// eslint-disable-next-line compat/compat
	return new Intl.RelativeTimeFormat( 'en' ).format( value, units[ i ] );
}
