<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * Turns whatever was uploaded into a UTF-8 CSV file on the same disk, so the
 * rest of the wizard only ever deals with one streaming format.
 *
 *  - .xlsx / .xlsm / .ods are streamed row by row (OpenSpout, constant memory)
 *  - legacy .xls is read with PhpSpreadsheet when it is installed (loaded in memory)
 *  - .csv / .txt in another encoding (UTF-16, Windows-1252, …) are transcoded
 *  - UTF-8 CSV files are used as they are
 */
class FileNormalizer
{
    public const STREAMING_SPREADSHEETS = ['xlsx', 'xlsm', 'ods'];

    public const SPREADSHEETS = ['xlsx', 'xlsm', 'ods', 'xls'];

    public static function isSpreadsheet(string $path): bool
    {
        return in_array(self::extension($path), self::SPREADSHEETS, true);
    }

    /**
     * Sheet names of a spreadsheet, or an empty list for CSV files.
     *
     * @return list<string>
     */
    public function sheets(string $disk, string $path): array
    {
        if (! self::isSpreadsheet($path)) {
            return [];
        }

        $local = $this->download($disk, $path);

        try {
            if (self::extension($path) === 'xls') {
                return array_values($this->xlsReader()->listWorksheetNames($local));
            }

            $reader = self::extension($path) === 'ods' ? new OdsReader : new XlsxReader;
            $reader->open($local);
            $names = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $names[] = $sheet->getName();
            }

            $reader->close();

            return $names;
        } finally {
            @unlink($local);
        }
    }

    /**
     * @return string the path of a UTF-8 CSV on $disk (the input path itself when nothing had to change)
     */
    public function normalize(string $disk, string $path, int $sheet = 0): string
    {
        if (self::isSpreadsheet($path)) {
            return $this->convertSpreadsheet($disk, $path, $sheet);
        }

        return $this->ensureUtf8($disk, $path);
    }

    protected function convertSpreadsheet(string $disk, string $path, int $sheet): string
    {
        $local = $this->download($disk, $path);
        $csv = tempnam(sys_get_temp_dir(), 'iw-csv-');

        if ($csv === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        $out = fopen($csv, 'w');

        if ($out === false) {
            throw new RuntimeException('Unable to open a temporary file.');
        }

        try {
            if (self::extension($path) === 'xls') {
                $this->writeXls($local, $sheet, $out);
            } else {
                $this->writeStreaming($local, self::extension($path), $sheet, $out);
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new RuntimeException(__('filament-import-wizard::errors.spreadsheet_unreadable', ['reason' => $exception->getMessage()]), previous: $exception);
        } finally {
            fclose($out);
            @unlink($local);
        }

        return $this->store($disk, $path, $csv);
    }

    /**
     * @param  resource  $out
     */
    protected function writeStreaming(string $local, string $extension, int $sheetIndex, $out): void
    {
        $reader = $extension === 'ods' ? new OdsReader : new XlsxReader;
        $reader->open($local);
        $index = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($index++ !== $sheetIndex) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $this->writeRow($out, array_map(fn (Cell $cell): mixed => $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue(), $row->getCells()));
            }

            break;
        }

        $reader->close();
    }

    /**
     * @param  resource  $out
     */
    protected function writeXls(string $local, int $sheetIndex, $out): void
    {
        $spreadsheet = $this->xlsReader()->load($local);
        $worksheet = $spreadsheet->getSheet($sheetIndex);

        foreach ($worksheet->getRowIterator() as $row) {
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(false);
            $values = [];

            foreach ($cells as $cell) {
                try {
                    $value = $cell->getCalculatedValue();
                } catch (\Throwable) {
                    $value = $cell->getValue(); // a formula PhpSpreadsheet cannot evaluate: keep what is stored
                }

                // Dates are numbers with a date format: write them the way every other format does.
                if (is_numeric($value) && Date::isDateTime($cell)) {
                    $value = Date::excelToDateTimeObject((float) $value);
                }

                $values[] = $value;
            }

            $this->writeRow($out, $values);
        }

        $spreadsheet->disconnectWorksheets();
    }

    protected function xlsReader(): Xls
    {
        if (! class_exists(Xls::class)) {
            throw new RuntimeException(__('filament-import-wizard::errors.xls_needs_phpspreadsheet'));
        }

        $reader = new Xls;
        // Cell formats are needed to tell dates from numbers.
        $reader->setReadDataOnly(false);

        return $reader;
    }

    /**
     * @param  resource  $out
     * @param  array<int|string, mixed>  $cells
     */
    protected function writeRow($out, array $cells): void
    {
        $values = array_map(fn (mixed $value): string => $this->format($value), array_values($cells));

        // Skip fully blank rows, which spreadsheets pad with.
        if (array_filter($values, fn (string $value): bool => $value !== '') === []) {
            return;
        }

        fputcsv($out, $values, ',', '"', '');
    }

    protected function format(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    protected function ensureUtf8(string $disk, string $path): string
    {
        $storage = Storage::disk($disk);
        $stream = $storage->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read [{$path}] on disk [{$disk}].");
        }

        $sample = (string) fread($stream, 65536);
        fclose($stream);

        $encoding = $this->detectEncoding($sample);

        if ($encoding === null) {
            return $path;
        }

        $local = $this->download($disk, $path);
        $csv = tempnam(sys_get_temp_dir(), 'iw-utf8-');
        $in = fopen($local, 'r');
        $out = $csv === false ? false : fopen($csv, 'w');

        if ($csv === false || $in === false || $out === false) {
            throw new RuntimeException('Unable to transcode the file.');
        }

        try {
            stream_filter_append($in, 'convert.iconv.'.$encoding.'/UTF-8//IGNORE', STREAM_FILTER_READ);
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
            @unlink($local);
        }

        return $this->store($disk, $path, $csv);
    }

    /**
     * @return string|null the source encoding, or null when the sample is already UTF-8
     */
    protected function detectEncoding(string $sample): ?string
    {
        if (str_starts_with($sample, "\xFF\xFE")) {
            return 'UTF-16LE';
        }

        if (str_starts_with($sample, "\xFE\xFF")) {
            return 'UTF-16BE';
        }

        // BOM-less UTF-16: every other byte of mostly-ASCII text is NUL.
        if (str_contains($sample, "\0")) {
            $oddNuls = 0;
            $evenNuls = 0;

            foreach (str_split(substr($sample, 0, 512)) as $index => $byte) {
                if ($byte === "\0") {
                    $index % 2 === 0 ? $evenNuls++ : $oddNuls++;
                }
            }

            return $oddNuls >= $evenNuls ? 'UTF-16LE' : 'UTF-16BE';
        }

        // A sample can end in the middle of a multi-byte character; trim up to 3 bytes before judging.
        for ($trim = 0; $trim <= 3; $trim++) {
            if (mb_check_encoding(substr($sample, 0, strlen($sample) - $trim), 'UTF-8')) {
                return null;
            }
        }

        return 'Windows-1252';
    }

    /**
     * Copy the local file onto the disk next to the original and remove the local copy.
     */
    protected function store(string $disk, string $originalPath, string $local): string
    {
        $target = (string) preg_replace('/\.[^.\/]+$/', '', $originalPath).'-'.bin2hex(random_bytes(4)).'.csv';
        $stream = fopen($local, 'r');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the converted file.');
        }

        try {
            Storage::disk($disk)->put($target, $stream);
        } finally {
            fclose($stream);
            @unlink($local);
        }

        return $target;
    }

    protected function download(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        $local = tempnam(sys_get_temp_dir(), 'iw-src-');

        if (! is_resource($stream) || $local === false) {
            throw new RuntimeException("Unable to read [{$path}] on disk [{$disk}].");
        }

        file_put_contents($local, $stream);
        fclose($stream);

        return $local;
    }

    protected static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
}
