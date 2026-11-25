<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use Illuminate\Support\Facades\DB;
use Tpetry\QueryExpressions\Function\Aggregate\Sum;
use Tpetry\QueryExpressions\Function\Conditional\Coalesce;
use Tpetry\QueryExpressions\Language\Alias;
use Tpetry\QueryExpressions\Operator\Arithmetic\Add;
use Tpetry\QueryExpressions\Operator\Arithmetic\Subtract;
use Tpetry\QueryExpressions\Value\Number;

class InventoryManualMovement extends InventoryMovement
{
    #[\Override]
    public function getRules(): array
    {
        return [
            'fromInventoryTransactionType' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->fromInventoryTransactionType->canBeNegative() && $this->fromLocationAfterQuantity() < 0) {
                        $fail(sprintf(
                            "The %s inventory location's %s stock would drop below zero.",
                            $this->fromInventoryLocation->getUiLabel(),
                            $this->fromInventoryTransactionType->typeAsLabel(),
                        ));
                    }
                },
            ],
            'toInventoryTransactionType' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->toInventoryTransactionType->canBeNegative() && $this->toLocationAfterQuantity() < 0) {
                        $fail(sprintf(
                            'The %s inventory location stock of %s would drop below zero.',
                            $this->toInventoryLocation->getUiLabel(),
                            $this->toInventoryTransactionType->typeAsLabel(),
                        ));
                    }

                    if ($this->fromInventoryLocation->id !== $this->toInventoryLocation->id) {
                        $fail('The from and to inventory locations must be the same.');
                    }

                    if ($this->isManualMovement() && (
                        !in_array($this->fromInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes()) ||
                        !in_array($this->toInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes())
                    )) {
                        $fail('Can not move between these inventory types.');
                    }
                },
            ],
        ];
    }

    public function fromLocationAfterQuantity(): int
    {
        return (int)DB::table(Table::INVENTORYTRANSACTIONS)
            ->select(new Alias(new Subtract(new Coalesce([new Sum('quantity'), new Number(0)]), new Number($this->quantity)), 'quantity'))
            ->where('type', $this->fromInventoryTransactionType->value)
            ->where('inventoryItemId', $this->inventoryItemId)
            ->where('inventoryLocationId', $this->fromInventoryLocation->id)
            ->value('quantity');
    }

    public function isManualMovement(): bool
    {
        return $this->lineItemId === null && $this->transferId === null;
    }

    public function toLocationAfterQuantity(): int
    {
        return (int)DB::table(Table::INVENTORYTRANSACTIONS)
            ->select(new Alias(new Add(new Coalesce([new Sum('quantity'), new Number(0)]), new Number($this->quantity)), 'quantity'))
            ->where('type', $this->toInventoryTransactionType->value)
            ->where('inventoryItemId', $this->inventoryItemId)
            ->where('inventoryLocationId', $this->toInventoryLocation->id)
            ->value('quantity');
    }
}
