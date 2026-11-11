<?php

namespace TidyOrg\Inventory\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use TidyOrg\Inventory\Models\Item;

class UpdateItem
{
    public function __construct(private StoreItemPhoto $storePhoto)
    {
        //
    }

    /**
     * @param  array{name?: string, type?: string, parent_id?: int|null, metadata?: array<string, string>|null, photo?: UploadedFile|null}  $data
     */
    public function handle(Item $item, array $data, bool $removePhoto = false): Item
    {
        $photo = Arr::pull($data, 'photo');

        $item->update(Arr::only($data, ['name', 'type', 'parent_id', 'metadata']));

        if ($photo instanceof UploadedFile) {
            $this->storePhoto->handle($item, $photo);
        } elseif ($removePhoto) {
            $this->storePhoto->remove($item);
        }

        return $item;
    }
}
