<?php

$vendorDirectories = [
    '.phan/stubs',
    'upload/system/library/phpspreadsheet',
];

return [
    'target_php_version' => '7.3',
    'directory_list' => [
        'upload',
        ...$vendorDirectories,
    ],
    'exclude_analysis_directory_list' => $vendorDirectories,
    'plugins' => [
        'AlwaysReturnPlugin',
        'UnreachableCodePlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
    ],
    'dead_code_detection' => !in_array('--language-server-on-stdin', $GLOBALS['argv']),
    'unused_variable_detection' => true,
    'redundant_condition_detection' => true,
    'suppress_issue_types' => [
        'PhanUnusedProtectedMethodParameter',
        'PhanUnusedPublicMethodParameter',
        'PhanUnusedVariableCaughtException',
        'PhanUnreferencedClass',
    ],
];
