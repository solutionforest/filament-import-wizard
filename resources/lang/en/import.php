<?php

return [
    'title' => 'Import :label from a file',
    'steps' => ['upload' => 'Upload', 'map' => 'Map', 'review' => 'Review', 'import' => 'Import'],
    'upload' => [
        'label' => 'CSV or Excel file',
        'helper' => 'Drop a CSV, XLSX or ODS file here (first sheet, first row = headers). Large files are read in slices, so size is only limited by your upload settings.',
        'sheet' => 'Sheet',
        'empty' => 'The file has no header row.',
    ],
    'map' => [
        'heading' => 'Map columns',
        'description' => 'Match the columns in your file to the fields below. We pre-filled the ones we recognised.',
        'required' => 'Please map a file column to :column.',
        'sample' => 'e.g. :values',
        'continue' => 'Continue',
    ],
    'review' => [
        'checking' => 'Checking… :checked of :total rows',
        'ready' => 'All :total rows checked',
        'valid' => ':count rows are ready to import',
        'invalid' => ':count rows have problems (rows that fail validation are skipped on import)',
        'row' => 'Row :row',
        'import' => 'Import :count rows',
        'back' => 'Back to mapping',
    ],
    'import' => [
        'importing' => 'Importing… :processed of :total rows',
        'done' => 'Import finished: :successful of :total rows imported',
        'failed' => ':count rows failed. Download them from the notification.',
    ],
    'start_over' => 'Start over',
    'started_over' => 'Import reset',
];
