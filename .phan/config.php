<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'][] = '../CommunityConfiguration/src';
$cfg['exclude_analysis_directory_list'][] = '../CommunityConfiguration/src';

$cfg['directory_list'][] = '../TestKitchen/includes';
$cfg['exclude_analysis_directory_list'][] = '../TestKitchen/includes';

// Don't stub TestKitchen's SDK if the real extension is present. Anchored to this
// file rather than the working directory, unlike the paths phan resolves above.
if ( file_exists( __DIR__ . '/../../TestKitchen/includes/Sdk/ExperimentManagerInterface.php' ) ) {
	$cfg['exclude_file_list'] = array_merge(
		$cfg['exclude_file_list'],
		[
			'.phan/stubs/ExperimentInterface.php',
			'.phan/stubs/ExperimentManagerInterface.php',
		]
	);
}

return $cfg;
