<?php

namespace SolutionForest\FilamentImportWizard\Support;

use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Streams the first sheet of an Excel / OpenDocument file into a CSV file on
 * the same disk, row by row, so the rest of the wizard only ever deals with CSV.
 */
class SpreadsheetConverter
{
    public const EXTENSIONS = ['xlsx', 'xlsm', 'ods'];

    public static function supports(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * @return string path of the generated CSV (on the same disk)
     */
    public function convert(string $disk, string $path): string
    {
        $storage = Storage::disk($disk);
        $local = tempnam(sys_get_temp_dir(), 'iw-src-');
        file_put_contents($local, $storage->readStream($path));

        $reader = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'ods' ? new OdsReader : new XlsxReader;
        $csvPath = preg_replace('/\.[^.]+$/', '', $path).'-'.uniqid().'.csv';
        $out = fopen('php://temp/maxmemory:1048576', 'w+');

        try {
            $reader->open($local);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($cell): string => $this->format($cell->getValue()), $row->getCells());

                    if (array_filter($cells, 'filled') === []) {
                        continue;
                    }

                    fputcsv($out, $cells, ',', '"', '');
                }

                break; // first sheet only
            }

            $reader->close();
            rewind($out);
            $storage->put($csvPath, $out);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to read the spreadsheet: '.$exception->getMessage(), previous: $exception);
        } finally {
            fclose($out);
            @unlink($local);
        }

        return $csvPath;
    }

    protected function format(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
