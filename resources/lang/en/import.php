<?php

return [
    'title' => 'Import :label from a file',
    'steps' => ['upload' => 'Upload', 'map' => 'Map', 'review' => 'Review', 'import' => 'Import'],
    'upload' => [
        'label' => 'CSV or Excel file',
        'helper' => 'CSV, XLSX, XLS or ODS. The first row must hold the column headers. Large files are processed in slices.',
        'sheet' => 'Sheet',
        'drop' => 'Drop a file here or click to browse',
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
        'submit_for_approval' => 'Submit for approval',
        'back' => 'Back to mapping',
    ],
    'import' => [
        'importing' => 'Importing… :processed of :total rows',
        'done' => 'Import finished: :successful of :total rows imported',
        'failed' => ':count rows failed. Download them from the notification.',
    ],
    'approval' => [
        'submitted' => 'Submitted for approval',
        'hint' => 'The file was checked and is waiting for an approver. You will be notified when the import has run. Progress and history are in the Import audit page.',
    ],
    'start_over' => 'Start over',
    'started_over' => 'Import reset',
];
