<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

$cfg['directory_list'] = [
	'src',
	'vendor/psr',
];

$cfg['file_list'] = [
	'run.php',
];

$cfg = [
	'enable_extended_internal_return_type_plugins' => true,
	'enable_include_path_checks' => true,

	'dead_code_detection' => true,
	'dead_code_detection_prefer_false_negative' => true,
] + $cfg;

$cfg['plugins'] = array_merge(
	$cfg['plugins'],
	[
		'StrictLiteralComparisonPlugin',
		'DollarDollarPlugin',
		'UnknownElementTypePlugin',
		'StrictComparisonPlugin',
	]
);

return $cfg;
