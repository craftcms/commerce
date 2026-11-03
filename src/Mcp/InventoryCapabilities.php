<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresPermission;
use CraftCms\Cms\Mcp\McpActor;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\InventoryManagement;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

readonly class InventoryCapabilities
{
    use CallsCommerce;

    public function __construct(private McpActor $actor, private InventoryManagement $inventory)
    {
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.inventory.list', description: 'Lists stock levels at an inventory location. itemId is an inventory item ID, not a variant ID.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('commerce-manageInventoryStockLevels')]
    public function list(int $locationId, ?int $itemId = null, #[Schema(minimum: 1, maximum: 100)] int $limit = 100, #[Schema(minimum: 0)] int $offset = 0): array
    {
        return $this->call(fn() => $this->inventory->levels($this->actor->user(), $locationId, $itemId, $limit, $offset));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.inventory.history', description: 'Lists inventory ledger entries for one item and location, newest first.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('commerce-manageInventoryStockLevels')]
    public function history(int $locationId, int $itemId, #[Schema(minimum: 1, maximum: 100)] int $limit = 100, #[Schema(minimum: 0)] int $offset = 0): array
    {
        return $this->call(fn() => $this->inventory->history($this->actor->user(), $locationId, $itemId, $limit, $offset));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.inventory.update', description: 'Sets or adjusts a manual stock bucket or onHand through the inventory ledger. Check levels before retrying; adjustments are not idempotent.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-manageInventoryStockLevels')]
    public function update(int $locationId, int $itemId, string $type, InventoryUpdateQuantityType $action, int $quantity, string $note = ''): array
    {
        return $this->call(fn() => $this->inventory->update($this->actor->user(), $locationId, $itemId, $type, $action, $quantity, $note));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.inventory.move', description: 'Moves stock between manual buckets at one location. Use transfers to move stock between locations.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-manageInventoryStockLevels')]
    public function move(int $locationId, int $itemId, InventoryTransactionType $from, InventoryTransactionType $to, #[Schema(minimum: 1)] int $quantity, string $note = ''): array
    {
        return $this->call(fn() => $this->inventory->move($this->actor->user(), $locationId, $itemId, $from, $to, $quantity, $note));
    }
}
