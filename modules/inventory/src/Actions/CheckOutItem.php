<?php

namespace TidyOrg\Inventory\Actions;

use App\Modules\Person;
use TidyOrg\Inventory\Models\Item;

class CheckOutItem
{
    /**
     * Record that a person from the organization's people directory has the item.
     */
    public function handle(Item $item, Person $person): Item
    {
        $item->update([
            'checked_out_to' => $person->id,
            'checked_out_at' => now(),
        ]);

        return $item;
    }
}
