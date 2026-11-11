<?php

namespace TidyOrg\Inventory\Actions;

use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use TidyOrg\Inventory\Models\Item;

class CreateItem
{
    public function __construct(private StoreItemPhoto $storePhoto)
    {
        //
    }

    /**
     * @param  array{name: string, type: string, parent_id?: int|null, metadata?: array<string, string>|null, photo?: UploadedFile|null}  $data
     */
    public function handle(Team $team, array $data): Item
    {
        $photo = Arr::pull($data, 'photo');

        $item = Item::create([...Arr::only($data, ['name', 'type', 'parent_id', 'metadata']), 'team_id' => $team->id]);

        if ($photo instanceof UploadedFile) {
            $this->storePhoto->handle($item, $photo);
        }

        return $item;
    }
}
