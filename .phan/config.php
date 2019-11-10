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

	'redundant_condition_detection' => true,

	'plugins' => [
		'UnreachableCodePlugin',
		'PregRegexCheckerPlugin',
		'UnusedSuppressionPlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
	],
];
