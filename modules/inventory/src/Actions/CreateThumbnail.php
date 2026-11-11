<?php

namespace Tessera\Inventory\Actions;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateThumbnail
{
    /**
     * The longest edge of a thumbnail, in pixels.
     */
    public const SIZE = 300;

    /**
     * Store a small JPEG copy of a photo and return its path.
     *
     * Returns null when the photo cannot be read or PHP has no GD extension;
     * the full-size photo is shown in its place.
     */
    public function handle(string $photoPath): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $contents = Storage::get($photoPath);

        if ($contents === null) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::SIZE / max($width, $height));

        $thumbnail = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagefill($thumbnail, 0, 0, (int) imagecolorallocate($thumbnail, 255, 255, 255));
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, imagesx($thumbnail), imagesy($thumbnail), $width, $height);
        unset($image);
        $thumbnail = $this->orient($thumbnail, $contents);

        ob_start();
        imagejpeg($thumbnail, null, 80);
        $jpeg = (string) ob_get_clean();

        $path = $this->pathFor($photoPath);

        return Storage::put($path, $jpeg) ? $path : null;
    }

    /**
     * Copy an existing thumbnail for a copy of its photo.
     */
    public function copy(string $thumbPath, string $photoPath): ?string
    {
        $path = $this->pathFor($photoPath);

        return Storage::copy($thumbPath, $path) ? $path : null;
    }

    private function pathFor(string $photoPath): string
    {
        return dirname($photoPath).'/thumbs/'.pathinfo($photoPath, PATHINFO_FILENAME).'-'.Str::lower(Str::random(8)).'.jpg';
    }

    /**
     * Apply the rotation a camera recorded in the photo's EXIF data.
     */
    private function orient(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+');

        if ($stream === false) {
            return $image;
        }

        fwrite($stream, $contents);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            6, 7 => imagerotate($image, -90, 0),
            5, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
