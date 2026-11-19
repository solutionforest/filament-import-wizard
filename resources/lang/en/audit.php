<?php

return [
    'title' => 'Import audit',
    'navigation' => 'Import audit',
    'columns' => [
        'when' => 'When',
        'uploaded_by' => 'Uploaded by',
        'file' => 'File',
        'rows' => 'Rows',
        'problems' => 'Problems',
        'status' => 'Status',
    ],
    'status' => [
        'uploaded' => 'Uploaded',
        'reviewed' => 'Reviewed',
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'importing' => 'Importing',
        'completed' => 'Completed',
        'completed_with_failures' => 'Completed with failures',
    ],
    'actions' => [
        'history' => 'History',
        'close' => 'Close',
        'approve' => 'Approve',
        'approve_description' => 'The file will be imported now, as its uploader.',
        'reject' => 'Reject',
        'note' => 'Note (optional)',
        'reason' => 'Reason',
    ],
    'history' => [
        'heading' => 'Import #:id',
        'checksum' => 'SHA-256',
        'summary' => ':rows rows · :problems with problems',
        'result' => ':imported imported, :failed failed',
        'user' => 'user #:id',
    ],
    'notifications' => [
        'approved' => 'Import approved and started',
        'rejected' => 'Import rejected',
        'cannot_decide' => 'You cannot approve or reject this import.',
    ],
    'errors' => [
        'already_final' => 'Audit #:id is already :status.',
        'no_column_map' => 'Audit #:id has no reviewed column map.',
    ],
];
