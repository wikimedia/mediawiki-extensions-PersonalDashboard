<?php
/**
 * Slug alias fixture: one extension's file.
 *
 * @file
 */

$personalDashboardSlugAliases = [];

$personalDashboardSlugAliases['en'] = [
	'impact' => [ 'impact', 'my-impact' ],
	'review' => [ 'review' ],
];

$personalDashboardSlugAliases['de'] = [
	'impact' => [ 'wirkung', 'auswirkung' ],
];

// de-formal falls back to de, which supplies impact; this adds only its own.
$personalDashboardSlugAliases['de-formal'] = [
	'review' => [ 'pruefung' ],
];
