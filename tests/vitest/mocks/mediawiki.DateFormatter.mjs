export function formatDate( date ) {
	const formattedDate = date.toLocaleDateString( 'en-us', { day: '2-digit', month: 'long', year: 'numeric' } );
	const splitDate = formattedDate.split( ' ' );
	return `${ splitDate[ 1 ] } ${ splitDate[ 0 ] } ${ splitDate[ 2 ] }`;
}

export function formatTime( date ) {
	return date.toLocaleTimeString( 'en-us', { hour: 'numeric', minute: 'numeric' } );
}
