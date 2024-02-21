<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\enums;

use Craft;
use craft\commerce\base\EnumHelpersTrait;

enum InventoryMovementType: string
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

    // Unavailable since they are still incoming
    case INCOMING = 'incoming';

    /**
     * @return string
     */
    public function typeAsLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => Craft::t('commerce', 'Available'),
            self::RESERVED => Craft::t('commerce', 'Reserved'),
            self::DAMAGED => Craft::t('commerce', 'Damaged'),
            self::SAFETY => Craft::t('commerce', 'Safety'),
            self::QUALITY_CONTROL => Craft::t('commerce', 'Quality Control'),
            self::COMMITTED => Craft::t('commerce', 'Committed'),
            self::INCOMING => Craft::t('commerce', 'Incoming'),
            default => throw new \Exception('Invalid inventory movement type: ' . $this->value)
        };
    }

    /**
     * Can this transaction type go into the negative sum?
     *
     * @return bool
     */
    public function canBeNegative(): bool
    {
        return $this === self::AVAILABLE || $this === self::COMMITTED || $this === self::INCOMING;
    }

    /**
     * @return InventoryMovementType[]
     */
    public static function onHand(): array
    {
        // on hand is unavailable + available + committed
        return array_merge(
            self::unavailable(),
            self::available(),
            self::committed()
        );
    }

    /**
     * @return InventoryMovementType[]
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
     * @return InventoryMovementType[]
     */
    public static function available(): array
    {
        return [
            self::AVAILABLE,
        ];
    }

    /**
     * @return InventoryMovementType[]
     */
    public static function incoming(): array
    {
        return [
            self::INCOMING,
        ];
    }

    /**
     * @return InventoryMovementType[]
     */
    public static function committed(): array
    {
        return [
            self::COMMITTED,
        ];
    }

    /**
     * These are the types that can be manually moved between (Outside a transfer or purchase order or fulfillment).
     *
     * @return InventoryMovementType[]
     */
    public static function allowedManualMovementTypes(): array
    {
        return [
            // Unavailable
            ...self::unavailable(),

            //available
            ...self::available(),
        ];
    }

    /**
     * These are the types that can be manually moved between (Outside a transfer or purchase order or fulfillment).
     *
     * @return InventoryMovementType[]
     */
    public static function allowedManualAdjustmentTypes(): array
    {
        return [
            // Unavailable
            ...self::unavailable(),

            //available
            ...self::available(),
        ];
    }
}
