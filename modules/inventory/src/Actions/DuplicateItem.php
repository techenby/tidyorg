<?php

namespace TidyOrg\Inventory\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TidyOrg\Inventory\Models\Item;

class DuplicateItem
{
    public function __construct(private CreateThumbnail $createThumbnail)
    {
        //
    }

    /**
     * Create copies of an item beside it, each with its own copy of the photo.
     * A copy is never checked out, whatever the original is.
     *
     * @return Collection<int, Item>
     */
    public function handle(Item $item, int $count = 1): Collection
    {
        return Collection::times($count, function () use ($item): Item {
            $copy = $item->replicate(['photo_path', 'thumb_path', 'checked_out_to', 'checked_out_at']);
            $copy->save();

            if ($item->photo_path) {
                $extension = pathinfo($item->photo_path, PATHINFO_EXTENSION);
                $path = Item::storageDirectory($item->team_id).'/'.Str::slug($copy->name).'-'.$copy->id.'.'.$extension;

                Storage::copy($item->photo_path, $path);

                $copy->update([
                    'photo_path' => $path,
                    'thumb_path' => $item->thumb_path ? $this->createThumbnail->copy($item->thumb_path, $path) : null,
                ]);
            }

            return $copy;
        });
    }
}
