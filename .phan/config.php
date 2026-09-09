<?php

declare( strict_types = 1 );

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'][] = '../CommunityConfiguration/src';
$cfg['exclude_analysis_directory_list'][] = '../CommunityConfiguration/src';

$cfg['directory_list'][] = '../TestKitchen/includes';
$cfg['exclude_analysis_directory_list'][] = '../TestKitchen/includes';

$cfg['directory_list'][] = '../ORES/includes';
$cfg['exclude_analysis_directory_list'][] = '../ORES/includes';

$cfg['directory_list'][] = '../Wikibase/client/includes';
$cfg['exclude_analysis_directory_list'][] = '../Wikibase/client/includes';

// Drop a stub if the real code is present. Phan prefers the stub over the real
// definition, and then reports PhanRedefinedClassReference at every use of the
// class. CI installs these extensions, so there the real definition wins. The
// keys are anchored to this file rather than the working directory, unlike the
// paths phan resolves above.
$stubbedDependencies = [
	'/../../TestKitchen/includes/Sdk/ExperimentManagerInterface.php' => [
		'.phan/stubs/ExperimentInterface.php',
		'.phan/stubs/ExperimentManagerInterface.php',
	],
	'/../../Wikibase/client/includes' => [
		'.phan/stubs/DescriptionLookup.php',
	],
];

foreach ( $stubbedDependencies as $realPath => $stubs ) {
	if ( file_exists( __DIR__ . $realPath ) ) {
		$cfg['exclude_file_list'] = array_merge( $cfg['exclude_file_list'], $stubs );
	}
}

return $cfg;
