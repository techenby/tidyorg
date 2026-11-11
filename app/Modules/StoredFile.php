<?php

namespace App\Modules;

use Illuminate\Support\Facades\Storage;

/**
 * Links to uploaded files, whichever filesystem disk the deployment uses.
 */
class StoredFile
{
    /**
     * How long a temporary link stays valid, in minutes.
     */
    public const LINK_LIFETIME = 30;

    /**
     * A URL the browser can load the file from, or null when there is no file.
     *
     * Private disks (object storage, and the local disk when it serves files)
     * return a short-lived signed URL; other disks return their public URL.
     */
    public static function url(?string $path, ?string $disk = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $storage = Storage::disk($disk);

        if ($storage->providesTemporaryUrls()) {
            return $storage->temporaryUrl($path, now()->addMinutes(self::LINK_LIFETIME));
        }

        return $storage->url($path);
    }
}
