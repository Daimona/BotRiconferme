<?php

return [
	'directory_list' => array_merge(
		[
			'includes',
			'vendor/psr',
		],
		PHP_VERSION_ID < 80000 ? [ 'vendor/symfony/polyfill-php80' ] : []
	),
	'file_list' => [
		'run.php',
	],
	'exclude_analysis_directory_list' => [
		'vendor/'
	],

	'exclude_file_regex' => '@vendor/.*/[Tt]ests?/@',

	'enable_extended_internal_return_type_plugins' => true,
	'enable_include_path_checks' => true,
	'generic_types_enabled' => true,

	'null_casts_as_any_type' => false,
	'scalar_implicit_cast' => false,
	'dead_code_detection' => true,
	'dead_code_detection_prefer_false_negative' => true,

	'redundant_condition_detection' => true,

	'plugins' => [
		'UnreachableCodePlugin',
		'PregRegexCheckerPlugin',
		'UnusedSuppressionPlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
		'RedundantAssignmentPlugin',
		'StrictLiteralComparisonPlugin',
		'DollarDollarPlugin',
		'UnknownElementTypePlugin',
		'LoopVariableReusePlugin',
		'StrictComparisonPlugin',
		'SimplifyExpressionPlugin',
		'vendor/mediawiki/phan-taint-check-plugin/GenericSecurityCheckPlugin.php'
	],

	'suppress_issue_types' => [
		'SecurityCheck-LikelyFalsePositive'
	],
];
