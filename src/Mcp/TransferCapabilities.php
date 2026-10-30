<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresPermission;
use CraftCms\Cms\Mcp\McpActor;
use CraftCms\Commerce\Transfer\TransferManagement;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

readonly class TransferCapabilities
{
    use CallsCommerce;

    public function __construct(private McpActor $actor, private TransferManagement $transfers)
    {
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.transfers.details', description: 'Gets transfer detail UIDs and requested, accepted and rejected inventory quantities.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('commerce-manageInventoryTransfers')]
    public function details(int $id): array
    {
        return $this->call(fn() => $this->transfers->details($this->actor->user(), $id));
    }

    /**
     * @param list<array{inventoryItemId: int, quantity: int}> $items Complete replacement list of transfer details, maximum 100.
     * @return array<string, mixed>
     */
    #[McpTool(name: 'commerce.transfers.save', description: 'Creates a draft inventory transfer, or replaces an existing draft transfer details. Omit id to create. Read existing details before replacement.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-manageInventoryTransfers')]
    public function save(int $originLocationId, int $destinationLocationId, array $items, ?int $id = null): array
    {
        return $this->call(fn() => ['transfer' => ElementData::attributes($this->transfers->save($this->actor->user(), $originLocationId, $destinationLocationId, $items, $id))]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.transfers.dispatch', description: 'Marks a draft transfer pending and records inventory movements.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-manageInventoryTransfers')]
    public function dispatch(int $id): array
    {
        return $this->call(fn() => ['transfer' => ElementData::attributes($this->transfers->dispatch($this->actor->user(), $id))]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.transfers.receive', description: 'Receives pending inventory. quantities is keyed by detail UID with accept and/or reject integer quantities. Check details before retrying.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-manageInventoryTransfers')]
    public function receive(int $id, #[Schema(type: 'object')] array $quantities): array
    {
        return $this->call(fn() => ['transfer' => ElementData::attributes($this->transfers->receive($this->actor->user(), $id, $quantities))]);
    }
}
