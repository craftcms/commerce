<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Purchasable Store model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableStore extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var int|null
     */
    public ?int $purchasableId = null;

    /**
     * @var int|null
     */
    public ?int $storeId = null;

    /**
     * @var float|null
     */
    public ?float $price = null;

    /**
     * @var float|null
     */
    public ?float $promotionalPrice = null;

    /**
     * @var int|null
     */
    public ?int $stock = null;

    /**
     * @var bool
     */
    public bool $hasUnlimitedStock = false;

    /**
     * @var int|null
     */
    public ?int $minQty = null;

    /**
     * @var int|null
     */
    public ?int $maxQty = null;

    /**
     * @var bool
     */
    public bool $promotable = false;

    /**
     * @var bool
     */
    public bool $availableForPurchase = false;

    /**
     * @var bool
     */
    public bool $freeShipping = false;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['purchasableId', 'storeId'], 'required'];
        $rules[] = [['purchasableId', 'storeId', 'stock', 'minQty', 'maxQty'], 'integer'];
        $rules[] = [['price', 'promotionalPrice'], 'number'];
        $rules[] = [['hasUnlimitedStock', 'promotable', 'availableForPurchase', 'freeShipping'], 'boolean'];

        return $rules;
    }
}