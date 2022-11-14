<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Catalog Pricing Rule purchasable record.
 *
 * @property int $id
 * @property ActiveQueryInterface $purchasable
 * @property int $purchasableId
 * @property string $purchasableType
 * @property ActiveQueryInterface $catalogPricingRule
 * @property int $catalogPricingRuleId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRulePurchasable extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CATALOG_PRICING_RULES_PURCHASABLES;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['catalogPricingRuleId', 'purchasableId'], 'unique', 'targetAttribute' => ['catalogPricingRuleId', 'purchasableId']],
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPricingCatalogRule(): ActiveQueryInterface
    {
        return $this->hasOne(CatalogPricingRule::class, ['catalogPricingRuleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Purchasable::class, ['catalogPricingRuleId' => 'id']);
    }
}
