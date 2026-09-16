<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Enums;

use CraftCms\Commerce\Base\EnumHelpersTrait;

use function CraftCms\Cms\t;

enum InventoryTransactionType: string
{
    use EnumHelpersTrait;

    // Available for purchase
    case AVAILABLE = 'available';

    // Unavailable for purchase
    case RESERVED = 'reserved';
    case DAMAGED = 'damaged';
    case SAFETY = 'safety';
    case QUALITY_CONTROL = 'qualityControl';

    // Committed to ship
    case COMMITTED = 'committed';

    case FULFILLED = 'fulfilled';

    // Unavailable since they are still incoming
    case INCOMING = 'incoming';

    public function typeAsLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => t('Available', category: 'commerce'),
            self::RESERVED => t('Reserved', category: 'commerce'),
            self::DAMAGED => t('Damaged', category: 'commerce'),
            self::SAFETY => t('Safety', category: 'commerce'),
            self::QUALITY_CONTROL => t('Quality Control', category: 'commerce'),
            self::COMMITTED => t('Committed', category: 'commerce'),
            self::INCOMING => t('Incoming', category: 'commerce'),
            self::FULFILLED => t('Fulfilled', category: 'commerce'),
        };
    }

    /**
     * Can this transaction type go into the negative sum?
     */
    public function canBeNegative(): bool
    {
        return $this === self::AVAILABLE || $this === self::COMMITTED || $this === self::INCOMING;
    }

    /**
     * @return InventoryTransactionType[]
     */
    public static function onHand(): array
    {
        return array_merge(
            self::unavailable(),
            self::available(),
            self::committed(),
        );
    }

    /**
     * @return InventoryTransactionType[]
     */
    public static function unavailable(): array
    {
        return [
            self::RESERVED,
            self::DAMAGED,
            self::SAFETY,
            self::QUALITY_CONTROL,
        ];
    }

    /**
     * @return InventoryTransactionType[]
     */
    public static function available(): array
    {
        return [self::AVAILABLE];
    }

    /**
     * @return InventoryTransactionType[]
     */
    public static function incoming(): array
    {
        return [self::INCOMING];
    }

    /**
     * @return InventoryTransactionType[]
     */
    public static function committed(): array
    {
        return [self::COMMITTED];
    }

    /**
     * Types that can be manually moved between (outside a transfer, purchase order, or fulfillment).
     *
     * @return InventoryTransactionType[]
     */
    public static function allowedManualMoveTransactionTypes(): array
    {
        return [
            ...self::unavailable(),
            ...self::available(),
        ];
    }

    /**
     * Types that can be manually adjusted (outside a transfer, purchase order, or fulfillment).
     *
     * @return InventoryTransactionType[]
     */
    public static function allowedManualAdjustmentTypes(): array
    {
        return [
            ...self::unavailable(),
            ...self::available(),
        ];
    }
}
