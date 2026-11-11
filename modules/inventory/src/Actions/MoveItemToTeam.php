<?php

namespace Tessera\Inventory\Actions;

use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Tessera\Inventory\Models\Item;

class MoveItemToTeam
{
    /**
     * Hand an item to another organization, at the top level of its inventory.
     *
     * Anything inside the item stays behind at the top level, and the photo
     * moves into the destination organization's storage directory. The item
     * arrives checked in, because the person who had it belongs to the old organization.
     */
    public function handle(Item $item, Team $team): Item
    {
        $item->children()->update(['parent_id' => null]);

        $paths = [];

        foreach (['photo_path', 'thumb_path'] as $column) {
            $from = $item->{$column};

            if ($from === null) {
                continue;
            }

            $to = str_replace(Item::storageDirectory($item->team_id), Item::storageDirectory($team->id), $from);

            $paths[$column] = Storage::move($from, $to) ? $to : $from;
        }

        $item->update([
            ...$paths,
            'team_id' => $team->id,
            'parent_id' => null,
            'checked_out_to' => null,
            'checked_out_at' => null,
        ]);

        return $item;
    }
}
