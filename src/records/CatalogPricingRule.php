<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\base\StoreRecordTrait;
use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\elements\User;
use DateTime;
use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;

/**
 * Catalog Pricing Rule record.
 *
 * @property array|string $customerCondition
 * @property array|string $productCondition
 * @property array|string $variantCondition
 * @property array|string $purchasableCondition
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property string $description
 * @property float $applyAmount
 * @property string $apply
 * @property string $applyPriceType
 * @property bool $enabled
 * @property bool $isPromotionalPrice
 * @property int $id
 * @property int $storeId
 * @property-read ActiveQueryInterface $users
 * @property string $name
 * @property mixed $metadata
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRule extends ActiveRecord
{
    use StoreRecordTrait;

    public const APPLY_BY_PERCENT = 'byPercent';
    public const APPLY_BY_FLAT = 'byFlat';
    public const APPLY_TO_PERCENT = 'toPercent';
    public const APPLY_TO_FLAT = 'toFlat';
    public const APPLY_PRICE_TYPE_PRICE = 'price';
    public const APPLY_PRICE_TYPE_PROMOTIONAL_PRICE = 'promotionalPrice';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CATALOG_PRICING_RULES;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getUsers(): ActiveQueryInterface
    {
        return $this->hasMany(User::class, ['id' => 'userId'])->viaTable(Table::CATALOG_PRICING_RULES_USERS, ['catalogPricingRuleId' => 'id']);
    }
}
