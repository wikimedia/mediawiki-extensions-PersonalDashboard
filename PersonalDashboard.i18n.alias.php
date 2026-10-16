<?php
/**
 * Aliases for
 *   Special:PersonalDashboard
 * and for the module route slugs that Personal Dashboard's own module groups
 * declare in ./extension.json.
 *
 * The two globals are read by different things. LocalisationCache reads
 * $specialPageAliases, as it does for every extension. It does not read
 * $personalDashboardSlugAliases: readPHPFile() keeps only the keys it knows
 * and this is not one of them, so PersonalDashboardSlugAliasLookup reads the
 * file itself. Both are hand-maintained.
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'PersonalDashboard' => [ 'PersonalDashboard', 'Personal Dashboard' ],
];

/** Vietnamese (Tiếng Việt) */
$specialPageAliases['vi'] = [
	'PersonalDashboard' => [ 'Bảng_điều_khiển_cá_nhân' ],
];

/**
 * Module route slugs, in the shape $specialPageAliases uses: the key is the
 * canonical slug from the group config, the value is the slugs that resolve to
 * it in that language, and the first is the one the dashboard prefers.
 *
 * The canonical slug always resolves, in every language, so a slug needs an
 * entry here only to gain a localized slug. See ./docs/modules.md.
 */
$personalDashboardSlugAliases = [];

/** English (English) */
$personalDashboardSlugAliases['en'] = [
	'review' => [ 'review' ],
	'discussions' => [ 'discussions' ],
	'impact' => [ 'impact' ],
	'policies' => [ 'policies' ],
];
