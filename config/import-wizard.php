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
];
