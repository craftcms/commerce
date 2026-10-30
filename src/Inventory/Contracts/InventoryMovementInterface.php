<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Contracts;

use CraftCms\Commerce\Inventory\Data\InventoryItem;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;

interface InventoryMovementInterface
{
    public function getInventoryItem(): ?InventoryItem;

    public function getToInventoryLocation(): InventoryLocation;

    public function getFromInventoryLocation(): InventoryLocation;

    public function getToInventoryTransactionType(): InventoryTransactionType;

    public function getFromInventoryTransactionType(): InventoryTransactionType;

    public function getQuantity(): int;

    public function getTransferId(): ?int;

    public function getLineItemId(): ?int;

    public function getUserId(): ?int;

    public function getNote(): ?string;

    public function isValid(): bool;

    public function getInventoryMovementHash(): string;
}
