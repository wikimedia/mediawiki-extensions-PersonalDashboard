import { test, expect, vi } from 'vitest';
import { getRandomItems } from '/resources/ext.personalDashboard.common/utils.js';

test( 'getRandomItems', () => {
	const logWarn = vi.spyOn( mw.log, 'warn' ).mockImplementationOnce( () => {} );
	const array = Array.from( Array( 100 ).keys() );
	const samples = [
		getRandomItems( array, 5 ),
		getRandomItems( array, 5 ),
		getRandomItems( array, 200 )
	];

	// 3rd sample should trigger a warning
	expect( logWarn ).toHaveBeenCalledExactlyOnceWith( 'unable to randomly sample array: only 100 found' );

	// No two the same
	expect( samples[ 0 ].length ).toStrictEqual( 5 );
	expect( samples[ 1 ].length ).toStrictEqual( 5 );
	expect( samples[ 0 ] ).not.toStrictEqual( samples[ 1 ] );

	// pass through too-small arrays
	expect( array ).toStrictEqual( samples[ 2 ] );
} );
