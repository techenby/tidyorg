<?php

namespace TidyOrg\Inventory\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TidyOrg\Inventory\Models\Item;

class StoreItemPhoto
{
    public function __construct(private CreateThumbnail $createThumbnail)
    {
        //
    }

    /**
     * Store an uploaded photo for the item on the default disk, replacing any it already has.
     */
    public function handle(Item $item, UploadedFile $photo): void
    {
        $this->remove($item);

        $filename = Str::slug($item->name).'-'.$item->id.'.'.$photo->extension();

        $path = $photo->storeAs(Item::storageDirectory($item->team_id), $filename);

        if ($path === false) {
            return;
        }

        $item->update([
            'photo_path' => $path,
            'thumb_path' => $this->createThumbnail->handle($path),
        ]);
    }

    /**
     * Delete the item's photo and thumbnail.
     */
    public function remove(Item $item): void
    {
        $paths = array_filter([$item->photo_path, $item->thumb_path]);

        if ($paths === []) {
            return;
        }

        Storage::delete($paths);

        $item->update(['photo_path' => null, 'thumb_path' => null]);
    }
}
