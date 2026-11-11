<?php

namespace TidyOrg\Inventory\Actions;

use TidyOrg\Inventory\Models\Item;

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
