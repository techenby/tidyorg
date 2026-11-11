<?php

namespace Tessera\Inventory\Actions;

use Tessera\Inventory\Models\Item;

class CheckInItem
{
    /**
     * Record that the item is back.
     */
    public function handle(Item $item): Item
    {
        $item->update([
            'checked_out_to' => null,
            'checked_out_at' => null,
        ]);

        return $item;
    }
}
