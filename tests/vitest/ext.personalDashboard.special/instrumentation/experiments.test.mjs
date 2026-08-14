import { afterEach, expect, test, vi } from 'vitest';
import { withExperimentTagging } from '/resources/ext.personalDashboard.special/instrumentation/experiments.js';

afterEach( () => {
	mw.config.reset();
} );

test( 'send() merges a JSON action_context into a bare pageview call', () => {
	mw.config.set( 'wgPersonalDashboardModuleGroup', 'T426615' );
	mw.config.set( 'wgPersonalDashboardExperimentVariants', { T426615: 'treatment' } );
	const send = vi.fn();
	const instrument = withExperimentTagging( { send } );

	instrument.send( 'pageview' );

	expect( send ).toHaveBeenCalledWith(
		'pageview',
		// eslint-disable-next-line camelcase
		{ action_context: JSON.stringify( { module_group: 'T426615', module_variants: { T426615: 'treatment' } } ) },
		undefined
	);
} );

test( 'send() merges tags into caller-supplied interactionData without dropping existing fields', () => {
	mw.config.set( 'wgPersonalDashboardModuleGroup', 'T426615' );
	mw.config.set( 'wgPersonalDashboardExperimentVariants', { T426615: 'treatment' } );
	const send = vi.fn();
	const instrument = withExperimentTagging( { send } );
	const contextualAttributes = { page: {} };

	instrument.send(
		'click',
		// eslint-disable-next-line camelcase
		{ funnel_entry_token: 'abc123', element_friendly_name: 'Go to Recent Changes link' },
		contextualAttributes
	);

	expect( send ).toHaveBeenCalledWith(
		'click',
		{
			// eslint-disable-next-line camelcase
			funnel_entry_token: 'abc123',
			// eslint-disable-next-line camelcase
			element_friendly_name: 'Go to Recent Changes link',
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( { module_group: 'T426615', module_variants: { T426615: 'treatment' } } )
		},
		contextualAttributes
	);
} );

test( 'module_variants is an empty object in action_context when unenrolled', () => {
	mw.config.set( 'wgPersonalDashboardModuleGroup', 'default' );
	mw.config.set( 'wgPersonalDashboardExperimentVariants', {} );
	const send = vi.fn();
	const instrument = withExperimentTagging( { send } );

	instrument.send( 'pageview' );

	expect( send ).toHaveBeenCalledWith(
		'pageview',
		// eslint-disable-next-line camelcase
		{ action_context: JSON.stringify( { module_group: 'default', module_variants: {} } ) },
		undefined
	);
} );

test( 'module_variants carries every concurrently enrolled experiment', () => {
	mw.config.set( 'wgPersonalDashboardModuleGroup', 'T426615' );
	mw.config.set( 'wgPersonalDashboardExperimentVariants', { T426615: 'treatment', T430001: 'control' } );
	const send = vi.fn();
	const instrument = withExperimentTagging( { send } );

	instrument.send( 'pageview' );

	expect( send ).toHaveBeenCalledWith(
		'pageview',
		{
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( { module_group: 'T426615', module_variants: { T426615: 'treatment', T430001: 'control' } } )
		},
		undefined
	);
} );

test( 'an array-shaped wgPersonalDashboardExperimentVariants still serializes as an object', () => {
	// Guards against a stray [] from the wire (an empty PHP array without the
	// server-side object cast) reaching health metrics as JSON [] instead of {}.
	mw.config.set( 'wgPersonalDashboardModuleGroup', 'default' );
	mw.config.set( 'wgPersonalDashboardExperimentVariants', [] );
	const send = vi.fn();
	const instrument = withExperimentTagging( { send } );

	instrument.send( 'pageview' );

	expect( send ).toHaveBeenCalledWith(
		'pageview',
		// eslint-disable-next-line camelcase
		{ action_context: JSON.stringify( { module_group: 'default', module_variants: {} } ) },
		undefined
	);
} );
