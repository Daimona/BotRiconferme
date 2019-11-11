<?php

return [
	'directory_list' => [
		'includes',
		'vendor/'
	],
	'file_list' => [
		'run.php',
	],
	'exclude_analysis_directory_list' => [
		'vendor/'
	],

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
	],
];
