<?php

use Illuminate\Support\Facades\Storage;

/**
 * @param  array<int, array<int, string>>  $rows
 */
function storeCsv(array $rows, string $name = 'test.csv', string $delimiter = ',', string $bom = ''): string
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row, $delimiter, '"', '');
    }
    rewind($handle);
    Storage::disk('imports')->put($name, $bom.stream_get_contents($handle));

    return $name;
}
