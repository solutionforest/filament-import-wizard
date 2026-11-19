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
        'valid' => '{1} :count row is ready to import|[0,*] :count rows are ready to import',
        'invalid' => '{1} :count row has a problem (rows that fail validation are skipped on import)|[0,*] :count rows have problems (rows that fail validation are skipped on import)',
        'row' => 'Row :row',
        'import' => '{1} Import :count row|[0,*] Import :count rows',
        'failed' => 'The review could not be completed (:count part failed). Check that a queue worker is running, then start over.',
        'submit_for_approval' => 'Submit for approval',
        'back' => 'Back to mapping',
    ],
    'import' => [
        'importing' => 'Importing… :processed of :total rows',
        'done' => 'Import finished: :successful of :total rows imported',
        'failed' => '{1} :count row failed. Download it from the notification.|[0,*] :count rows failed. Download them from the notification.',
    ],
    'approval' => [
        'submitted' => 'Submitted for approval',
        'hint' => 'The file was checked and is waiting for an approver. You will be notified when the import has run. Progress and history are in the Import audit page.',
    ],
    'preview' => [
        'map' => 'Preview — how the first rows will be imported',
        'review' => '{1} First row, checked|[0,*] First :count rows, checked',
        'empty' => 'No rows to preview.',
    ],
    'notifications' => [
        'started' => 'Import started',
        'started_body' => '{1} :count row from :file is being imported.|[0,*] :count rows from :file are being imported.',
        'finished' => 'Import finished',
        'finished_body' => ':successful of :total rows imported.',
        'download_failed' => '{1} Download :count failed row|[0,*] Download :count failed rows',
    ],
    'action' => [
        'label' => 'Import',
        'close' => 'Close',
    ],
    'start_over' => 'Start over',
    'started_over' => 'Import reset',
    'model_importer' => [
        'completed' => 'Imported :count row(s).',
        'completed_with_failures' => 'Imported :count row(s); :failed failed.',
    ],
];
