import { test, expect, vi } from 'vitest';
import { getRandomItems } from '@resources/ext.personalDashboard.common/utils.js';

test( 'getRandomItems', () => {
	const warn = vi.spyOn( mw.log, 'warn' ).mockImplementation( () => {} );
	const array = Array.from( Array( 100 ).keys() );
	const samples = [
		getRandomItems( array, 5 ),
		getRandomItems( array, 5 ),
		getRandomItems( array, 200 )
	];
	// 3rd sample should trigger a warning
	expect( warn ).toHaveBeenCalled();
	// No two the same
	expect( samples[ 0 ].length ).toBe( 5 );
	expect( samples[ 1 ].length ).toBe( 5 );
	expect( samples[ 0 ] ).not.toEqual( samples[ 1 ] );
	// pass through too-small arrays
	expect( array ).toEqual( samples[ 2 ] );
} );
