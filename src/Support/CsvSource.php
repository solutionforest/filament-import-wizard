<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Generator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A forward-only, constant-memory CSV reader addressed by byte offset.
 *
 * Every read opens the stream, seeks to a stored offset and parses at most a
 * limited number of records, so a multi-gigabyte file can be inspected and
 * imported in small, resumable slices without ever being loaded into memory.
 */
class CsvSource
{
    public function __construct(
        protected string $disk,
        protected string $path,
        protected ?string $delimiter = null,
    ) {}

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter ??= $this->guessDelimiter();
    }

    /**
     * @return list<string>
     */
    public function getHeaders(): array
    {
        $stream = $this->open();

        try {
            $headers = fgetcsv($stream, null, $this->getDelimiter(), '"', '');

            if (! is_array($headers)) {
                return [];
            }

            return array_map(fn (?string $header): string => trim((string) $header), $headers);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Byte offset of the first data row (just after the header row).
     */
    public function getDataStartOffset(): int
    {
        $stream = $this->open();

        try {
            fgetcsv($stream, null, $this->getDelimiter(), '"', '');

            return (int) ftell($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Read up to $limit data rows starting at $offset, keyed by header.
     *
     * @param  list<string>  $headers
     * @return Generator<int, array<string, string>, mixed, int> returns the offset after the last row read
     */
    public function read(array $headers, int $offset, int $limit): Generator
    {
        $stream = $this->open($offset);
        $count = count($headers);
        $read = 0;

        try {
            while ($read < $limit && ($record = fgetcsv($stream, null, $this->getDelimiter(), '"', '')) !== false) {
                // A blank line is parsed as [null]; skip it like Filament does.
                if ($record === [null]) {
                    $offset = (int) ftell($stream);

                    continue;
                }

                $values = array_map(fn (?string $value): string => $value ?? '', array_slice(array_pad($record, $count, ''), 0, $count));

                yield array_combine($headers, $values);

                $read++;
                $offset = (int) ftell($stream);
            }
        } finally {
            fclose($stream);
        }

        return $offset;
    }

    /**
     * Split the file into slices of $chunkSize rows, returning one descriptor
     * per slice. Only byte offsets are kept in memory, never row data.
     *
     * @return Generator<int, array{offset: int, count: int}, mixed, int> returns the total number of rows
     */
    public function plan(int $chunkSize): Generator
    {
        $stream = $this->open($this->getDataStartOffset());
        $total = 0;
        $inChunk = 0;
        $chunkStart = (int) ftell($stream);

        try {
            while (($record = fgetcsv($stream, null, $this->getDelimiter(), '"', '')) !== false) {
                if ($record === [null]) {
                    if ($inChunk === 0) {
                        $chunkStart = (int) ftell($stream);
                    }

                    continue;
                }

                $inChunk++;
                $total++;

                if ($inChunk === $chunkSize) {
                    yield ['offset' => $chunkStart, 'count' => $inChunk];

                    $chunkStart = (int) ftell($stream);
                    $inChunk = 0;
                }
            }

            if ($inChunk > 0) {
                yield ['offset' => $chunkStart, 'count' => $inChunk];
            }
        } finally {
            fclose($stream);
        }

        return $total;
    }

    /**
     * Count the data rows by streaming through the file.
     */
    public function countRows(): int
    {
        $plan = $this->plan(10000);

        foreach ($plan as $_) {
            // Exhaust the generator to reach its return value.
        }

        return $plan->getReturn();
    }

    /**
     * @return resource
     */
    protected function open(int $offset = 0)
    {
        $stream = Storage::disk($this->disk)->readStream($this->path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to open [{$this->path}] on disk [{$this->disk}].");
        }

        $isSeekable = stream_get_meta_data($stream)['seekable'];

        // Skip the UTF-8 byte order mark that Excel adds to exports.
        if ($offset === 0 && fread($stream, 3) !== "\xEF\xBB\xBF") {
            rewind($stream);
        }

        if ($offset > 0) {
            if ($isSeekable) {
                fseek($stream, $offset);
            } else {
                // Non-seekable remote streams: read and discard up to the offset.
                $remaining = $offset;

                while ($remaining > 0 && ! feof($stream)) {
                    $remaining -= strlen((string) fread($stream, min(8192, $remaining)));
                }
            }
        }

        return $stream;
    }

    protected function guessDelimiter(): string
    {
        $stream = $this->open();
        $lines = [];

        while (count($lines) < 5 && ($line = fgets($stream)) !== false) {
            $lines[] = $line;
        }

        fclose($stream);

        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = array_sum(array_map(fn (string $line): int => substr_count($line, $candidate), $lines));

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
