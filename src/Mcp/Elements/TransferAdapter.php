<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp\Elements;

use CraftCms\Commerce\Transfer\Elements\Transfer;

class TransferAdapter extends CommerceElementAdapter
{
    public static function handle(): string
    {
        return 'transfers';
    }

    public static function elementType(): string
    {
        return Transfer::class;
    }

    protected function updateAttributesSchema(): array
    {
        return ['type' => 'object', 'properties' => (object)[], 'additionalProperties' => false];
    }

    protected function criteriaProperties(): array
    {
        return ['transferStatus' => ['type' => 'string'], 'originLocationId' => ['type' => 'integer'], 'destinationLocationId' => ['type' => 'integer']];
    }

    protected function notes(): array
    {
        return ['Use commerce.transfers tools to create transfers, replace draft details, dispatch and receive inventory. State changes must run Commerce inventory movements.'];
    }
}
