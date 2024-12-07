<?php

declare( strict_types = 1 );

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

	'strict_method_checking' => true,
	'strict_object_checking' => true,
	'strict_param_checking' => true,
	'strict_property_checking' => true,
	'strict_return_checking' => true,

	'dead_code_detection' => true,
	'dead_code_detection_prefer_false_negative' => true,

	'warn_about_undocumented_throw_statements' => true,
	'exception_classes_with_optional_throws_phpdoc' => [
		'LogicException',
		'RuntimeException',
	],
	'warn_about_undocumented_exceptions_thrown_by_invoked_functions' => true,
] + $cfg;

$cfg['plugins'] = array_merge(
	$cfg['plugins'],
	[
		'AlwaysReturnPlugin',
		'DeprecateAliasPlugin',
		'DollarDollarPlugin',
		'PHPDocRedundantPlugin',
		'PHPDocToRealTypesPlugin',
		'PreferNamespaceUsePlugin',
		'PrintfCheckerPlugin',
		'StrictComparisonPlugin',
		'StrictLiteralComparisonPlugin',
		'UnknownElementTypePlugin',
	]
);

// Infer these from composer.json.
unset( $cfg['minimum_target_php_version'], $cfg['target_php_version'] );

return $cfg;
