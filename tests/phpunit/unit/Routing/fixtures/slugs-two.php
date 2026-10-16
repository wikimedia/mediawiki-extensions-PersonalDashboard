<?php
/**
 * Slug alias fixture: a second extension's file, merged with the first.
 *
 * @file
 */

$personalDashboardSlugAliases = [];

$personalDashboardSlugAliases['de'] = [
	// A hand-edited file can hold anything; a slug that cannot be a URL
	// segment is dropped rather than registered.
	'discussions' => [ 'diskussionen', '', '   ', 42, null ],
	// The first file already declares impact for de, and declaration order
	// decides, so this must not take it over.
	'impact' => [ 'ueberschrieben' ],
];
