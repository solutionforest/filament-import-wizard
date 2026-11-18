<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use InvalidArgumentException;

/**
 * Describes one slice of a stored CSV file: where it is, and which rows
 * (by byte offset and count) a queued job should read. This is the whole
 * payload of an import job, so it stays small however big the file is.
 */
final readonly class Slice
{
    /**
     * @param  list<string>  $headers
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $delimiter,
        public array $headers,
        public int $offset,
        public int $count,
    ) {}

    /**
     * Slices travel through Filament's ImportDispatcher, which types a chunk as
     * a list of string-keyed string rows. A slice therefore encodes itself as a
     * chunk holding exactly one such row.
     *
     * @return list<array<string, string>>
     */
    public function toChunk(): array
    {
        return [[
            'disk' => $this->disk,
            'path' => $this->path,
            'delimiter' => $this->delimiter,
            'headers' => json_encode($this->headers, JSON_THROW_ON_ERROR),
            'offset' => (string) $this->offset,
            'count' => (string) $this->count,
        ]];
    }

    /**
     * @param  array<array-key, mixed>  $chunk  the output of {@see toChunk()}
     */
    public static function fromChunk(array $chunk): self
    {
        $row = $chunk[0] ?? null;

        if (! is_array($row)) {
            throw new InvalidArgumentException('Invalid import slice descriptor.');
        }

        $disk = $row['disk'] ?? null;
        $path = $row['path'] ?? null;
        $delimiter = $row['delimiter'] ?? null;
        $headers = is_string($row['headers'] ?? null) ? json_decode($row['headers'], true) : null;
        $offset = $row['offset'] ?? null;
        $count = $row['count'] ?? null;

        if (! is_string($disk) || ! is_string($path) || ! is_string($delimiter) || ! is_array($headers) || ! is_numeric($offset) || ! is_numeric($count)) {
            throw new InvalidArgumentException('Invalid import slice descriptor.');
        }

        return new self(
            $disk,
            $path,
            $delimiter,
            array_values(array_map(fn (mixed $header): string => is_string($header) ? $header : '', $headers)),
            (int) $offset,
            (int) $count,
        );
    }

    public function source(): CsvSource
    {
        return new CsvSource($this->disk, $this->path, $this->delimiter);
    }

    /**
     * @return list<array<string, string>>
     */
    public function rows(): array
    {
        return iterator_to_array($this->source()->read($this->headers, $this->offset, $this->count), false);
    }
}
