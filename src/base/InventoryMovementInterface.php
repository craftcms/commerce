<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLocation;

/**
 * Interface for the execution of inventory movement transactions to be executed.
 *
 * @since 5.0.0
 */
interface InventoryMovementInterface
{
    /**
     * @return InventoryItem
     */
    public function getInventoryItem(): ?InventoryItem;

    /**
     * @return InventoryLocation
     */
    public function getToInventoryLocation(): InventoryLocation;

    /**
     * @return InventoryLocation
     */
    public function getFromInventoryLocation(): InventoryLocation;

    /**
     * @return InventoryTransactionType
     */
    public function getToInventoryTransactionType(): InventoryTransactionType;

    /**
     * @return InventoryTransactionType
     */
    public function getFromInventoryTransactionType(): InventoryTransactionType;

    /**
     * @return int
     */
    public function getQuantity(): int;

    /**
     * @return int|null
     */
    public function getTransferId(): ?int;

    /**
     * @return int|null
     */
    public function getLineItemId(): ?int;

    /**
     * @return int|null
     */
    public function getUserId(): ?int;

    /**
     * @return string|null
     */
    public function getNote(): ?string;

    /**
     * @return bool
     */
    public function isValid(): bool;

    /**
     * @return string
     */
    public function getInventoryMovementHash(): string;
}
