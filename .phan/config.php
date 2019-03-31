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

	'plugins' => [
		'UnreachableCodePlugin',
		'PregRegexCheckerPlugin',
		'UnusedSuppressionPlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
	],
];
