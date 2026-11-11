<?php

namespace TidyOrg\Inventory\Actions;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use TidyOrg\Inventory\Models\Item;

class GenerateItemQrCode
{
    /**
     * Build a QR code that links back to the item.
     *
     * @return array{svg: string, name: string, url: string}
     */
    public function handle(Item $item): array
    {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd,
        );

        $url = route('inventory.link', $item);

        return [
            'svg' => (new Writer($renderer))->writeString($url),
            'name' => $item->name,
            'url' => $url,
        ];
    }
}
