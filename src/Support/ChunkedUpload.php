<?php

declare(strict_types=1);

namespace SolutionForest\FilamentImportWizard\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resumable, chunked uploads straight onto the configured disk, so the size of
 * a browser upload is no longer bounded by `upload_max_filesize` / `post_max_size`
 * (only each small chunk is).
 *
 * All state lives on the disk itself — a manifest and one object per chunk under
 * `{directory}/.chunks/{id}/` — never in the PHP session or local storage. With a
 * shared disk (S3, NFS) any web server can therefore receive any chunk, and any
 * server can assemble the file.
 */
class ChunkedUpload
{
    public const EXTENSIONS = ['csv', 'txt', 'xlsx', 'xlsm', 'ods', 'xls'];

    public function __construct(protected string $disk, protected string $directory) {}

    /**
     * Largest chunk (in bytes) the current PHP configuration accepts, capped at 8 MB.
     */
    public static function safeChunkSize(): int
    {
        $limits = array_filter([
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        ], fn (int $bytes): bool => $bytes > 0);

        // Leave room for the multipart envelope and other fields.
        return max(256 * 1024, (int) (min([8 * 1024 * 1024, ...$limits]) * 0.8));
    }

    /**
     * @return array{id: string, chunkSize: int, chunks: int}
     */
    public function begin(string $fileName, int $size, string $owner, ?int $maxKilobytes = null): array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsupported file type.');
        }

        if ($size < 1) {
            throw new InvalidArgumentException('The file is empty.');
        }

        if ($maxKilobytes !== null && $size > $maxKilobytes * 1024) {
            throw new InvalidArgumentException('The file is larger than the allowed size.');
        }

        $id = bin2hex(random_bytes(16));
        $chunkSize = self::safeChunkSize();
        $chunks = (int) ceil($size / $chunkSize);

        $this->storage()->put($this->manifestPath($id), json_encode([
            'name' => $this->safeName($fileName),
            'size' => $size,
            'owner' => $owner,
            'chunk_size' => $chunkSize,
            'chunks' => $chunks,
            'created_at' => time(),
        ], JSON_THROW_ON_ERROR));

        return ['id' => $id, 'chunkSize' => $chunkSize, 'chunks' => $chunks];
    }

    public function put(string $id, int $index, UploadedFile $chunk, string $owner): void
    {
        $manifest = $this->manifest($id, $owner);

        if ($index < 0 || $index >= $manifest['chunks']) {
            throw new InvalidArgumentException('Chunk index out of range.');
        }

        $isLast = $index === $manifest['chunks'] - 1;
        $expected = $isLast ? $manifest['size'] - $manifest['chunk_size'] * ($manifest['chunks'] - 1) : $manifest['chunk_size'];

        if ($chunk->getSize() !== $expected) {
            throw new InvalidArgumentException("Chunk {$index} has the wrong size.");
        }

        $stream = fopen($chunk->getRealPath(), 'r');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the uploaded chunk.');
        }

        try {
            $this->storage()->put($this->chunkPath($id, $index), $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Join the chunks into the final file and return its path on the disk.
     * Needs temporary local space equal to the file size while joining.
     */
    public function complete(string $id, string $owner): string
    {
        $manifest = $this->manifest($id, $owner);
        $storage = $this->storage();

        for ($index = 0; $index < $manifest['chunks']; $index++) {
            if (! $storage->exists($this->chunkPath($id, $index))) {
                throw new RuntimeException("Chunk {$index} is missing.");
            }
        }

        $local = tempnam(sys_get_temp_dir(), 'iw-join-');

        if ($local === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        $out = fopen($local, 'w');

        if ($out === false) {
            throw new RuntimeException('Unable to open a temporary file.');
        }

        try {
            for ($index = 0; $index < $manifest['chunks']; $index++) {
                $part = $storage->readStream($this->chunkPath($id, $index));

                if (! is_resource($part)) {
                    throw new RuntimeException("Chunk {$index} cannot be read.");
                }

                stream_copy_to_stream($part, $out);
                fclose($part);
            }

            fclose($out);

            if (filesize($local) !== $manifest['size']) {
                throw new RuntimeException('The assembled file does not match the announced size.');
            }

            $final = trim($this->directory, '/').'/'.$id.'-'.$manifest['name'];
            $in = fopen($local, 'r');

            if ($in === false) {
                throw new RuntimeException('Unable to read the assembled file.');
            }

            try {
                $storage->put($final, $in);
            } finally {
                fclose($in);
            }
        } finally {
            @unlink($local);
        }

        $storage->deleteDirectory($this->workPath($id));

        return $final;
    }

    /**
     * Remove abandoned uploads older than $hours.
     *
     * @return int number of uploads removed
     */
    public function prune(int $hours = 24): int
    {
        $storage = $this->storage();
        $removed = 0;

        foreach ($storage->directories(trim($this->directory, '/').'/.chunks') as $directory) {
            $manifestPath = $directory.'/manifest.json';
            $createdAt = 0;

            if ($storage->exists($manifestPath)) {
                $decoded = json_decode((string) $storage->get($manifestPath), true);
                $createdAt = is_array($decoded) && is_int($decoded['created_at'] ?? null) ? $decoded['created_at'] : 0;
            }

            if ($createdAt <= time() - $hours * 3600) {
                $storage->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return array{name: string, size: int, owner: string, chunk_size: int, chunks: int}
     */
    protected function manifest(string $id, string $owner): array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1 || ! $this->storage()->exists($this->manifestPath($id))) {
            throw new InvalidArgumentException('Unknown upload.');
        }

        $manifest = json_decode((string) $this->storage()->get($this->manifestPath($id)), true);

        if (
            ! is_array($manifest)
            || ! is_string($manifest['name'] ?? null)
            || ! is_int($manifest['size'] ?? null)
            || ! is_string($manifest['owner'] ?? null)
            || ! is_int($manifest['chunk_size'] ?? null)
            || ! is_int($manifest['chunks'] ?? null)
        ) {
            throw new InvalidArgumentException('Unknown upload.');
        }

        // Uploads belong to the user who started them.
        if (! hash_equals($manifest['owner'], $owner)) {
            throw new InvalidArgumentException('Unknown upload.');
        }

        return [
            'name' => $manifest['name'],
            'size' => $manifest['size'],
            'owner' => $manifest['owner'],
            'chunk_size' => $manifest['chunk_size'],
            'chunks' => $manifest['chunks'],
        ];
    }

    protected function safeName(string $fileName): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($fileName));

        return trim($name, '.-') === '' ? 'upload.csv' : $name;
    }

    protected function workPath(string $id): string
    {
        return trim($this->directory, '/').'/.chunks/'.$id;
    }

    protected function manifestPath(string $id): string
    {
        return $this->workPath($id).'/manifest.json';
    }

    protected function chunkPath(string $id, int $index): string
    {
        return $this->workPath($id).'/'.str_pad((string) $index, 8, '0', STR_PAD_LEFT).'.part';
    }

    protected function storage(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    protected static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
