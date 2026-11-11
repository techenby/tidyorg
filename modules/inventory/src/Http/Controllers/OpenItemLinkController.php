<?php

namespace Tessera\Inventory\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tessera\Inventory\Models\Item;

class OpenItemLinkController
{
    /**
     * Send a scanned QR code to the item, or to its contents when it holds anything.
     *
     * Responds with a 404 to anyone who may not view the item, so a link never
     * reveals whether another organization's item exists.
     */
    public function __invoke(Request $request, Item $item): RedirectResponse
    {
        abort_unless($request->user()->can('view', $item), 404);

        if (! $item->trashed() && $item->children()->exists()) {
            return to_route('inventory.index', ['current_team' => $item->team, 'parentId' => $item->id]);
        }

        return to_route('inventory.show', ['current_team' => $item->team, 'item' => $item]);
    }
}
