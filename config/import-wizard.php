<?php

return [
    /*
    | Where file-level checks (UniqueInFile) remember the rows they have seen
    | during Review. Review is spread over many requests, so with more than one
    | web server this MUST be a shared store:
    |
    |   'sqlite'   – a throw-away file in storage/app/import-wizard (single server)
    |   'database' – the application database (works behind a load balancer)
    */
    'seen_store' => env('IMPORT_WIZARD_SEEN_STORE', 'sqlite'),

    /*
    | Seconds a queued import slice may run (Horizon / queue:work --timeout must
    | be at least this). Lower `chunkSize()` instead of raising it for slow importers.
    */
    'job_timeout' => (int) env('IMPORT_WIZARD_JOB_TIMEOUT', 600),

    /*
    | Rows shown in the Map and Review preview tables.
    */
    'preview_rows' => (int) env('IMPORT_WIZARD_PREVIEW_ROWS', 10),
];
