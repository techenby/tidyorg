<?php

namespace App\Modules;

use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Collects an organization's data into an archive. Each module adds its own
 * records and files under a folder named after its key.
 */
class OrganizationExport
{
    public function __construct(private ZipArchive $archive, private string $folder = '')
    {
        //
    }

    /**
     * The same archive, writing into a folder of its own.
     */
    public function in(string $folder): self
    {
        return new self($this->archive, $this->folder.trim($folder, '/').'/');
    }

    /**
     * Add a list of records as a JSON file named after what they are, such as "items".
     *
     * @param  iterable<int, array<string, mixed>>  $records
     */
    public function records(string $name, iterable $records): void
    {
        $this->archive->addFromString(
            "{$this->folder}{$name}.json",
            (string) json_encode(
                is_array($records) ? array_values($records) : iterator_to_array($records, false),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /**
     * Add a file from the default disk, returning its path in the archive or null when it is missing.
     */
    public function file(?string $path): ?string
    {
        $contents = $path === null ? null : Storage::get($path);

        if ($contents === null) {
            return null;
        }

        $name = "{$this->folder}files/".basename((string) $path);

        $this->archive->addFromString($name, $contents);

        return $name;
    }
}
