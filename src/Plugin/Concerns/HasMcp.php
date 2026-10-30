<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Mcp\CapabilityRegistry;
use CraftCms\Cms\Mcp\Elements\ElementAdapterRegistry;
use CraftCms\Cms\Mcp\Events\CollectingAdminInstructions;
use CraftCms\Cms\Mcp\Events\ElementSerializing;
use CraftCms\Commerce\Mcp\ConfigurationCapabilities;
use CraftCms\Commerce\Mcp\ElementData;
use CraftCms\Commerce\Mcp\Elements\OrderAdapter;
use CraftCms\Commerce\Mcp\Elements\ProductAdapter;
use CraftCms\Commerce\Mcp\Elements\TransferAdapter;
use CraftCms\Commerce\Mcp\Elements\VariantAdapter;
use CraftCms\Commerce\Mcp\InventoryCapabilities;
use CraftCms\Commerce\Mcp\OrderCapabilities;
use CraftCms\Commerce\Mcp\ResourceCapabilities;
use CraftCms\Commerce\Mcp\StoreCapabilities;
use CraftCms\Commerce\Mcp\TransferCapabilities;
use Illuminate\Support\Facades\Event;

trait HasMcp
{
    private function registerMcp(): void
    {
        app(CapabilityRegistry::class)->register(ResourceCapabilities::class, ConfigurationCapabilities::class, InventoryCapabilities::class, OrderCapabilities::class, TransferCapabilities::class, StoreCapabilities::class);
        app(ElementAdapterRegistry::class)->register(ProductAdapter::class, VariantAdapter::class, OrderAdapter::class, TransferAdapter::class);
        Event::listen(ElementSerializing::class, ElementData::class);
        Event::listen(CollectingAdminInstructions::class, static function(CollectingAdminInstructions $event): void {
            $event->instructions[] = 'Commerce products, variants, orders (including carts), and transfers use elements.* tools. Read elements.schema before editing. Discover store-management types with commerce.resources.schema and administrator configuration with commerce.configuration.schema. Read current values and deletion effects before writes. Prices use the store currency; payment refunds use the transaction payment currency. Never retry a payment or inventory write without first checking its result.';
        });
    }
}
