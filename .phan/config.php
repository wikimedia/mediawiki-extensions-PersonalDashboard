<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['stub_files'] = array_values( array_unique( array_merge(
	$cfg['stub_files'] ?? [],
	[
		__DIR__ . '/stubs/JsonSchema.php',
		__DIR__ . '/stubs/MediaWikiDefinitions.php',
		__DIR__ . '/stubs/SkinMinerva.php',
	]
) ) );

return $cfg;
