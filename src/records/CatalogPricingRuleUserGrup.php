<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\UserGroup;
use yii\db\ActiveQueryInterface;

/**
 * Catalog Pricing Rule user group record.
 *
 * @property int $id
 * @property ActiveQueryInterface $userGroup
 * @property int $userGroupId
 * @property ActiveQueryInterface $catalogPricingRule
 * @property int $catalogPricingRuleId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRuleUserGrup extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CATALOG_PRICING_RULES_USERS;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['catalogPricingRuleId', 'userGroupId'], 'unique', 'targetAttribute' => ['catalogPricingRuleId', 'userGroupId']],
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
     * @noinspection PhpUnused
     */
    public function getUserGroup(): ActiveQueryInterface
    {
        return $this->hasOne(UserGroup::class, ['catalogPricingRuleId' => 'id']);
    }
}
