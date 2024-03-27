<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\elements\User;
use yii\db\ActiveQueryInterface;

/**
 * Catalog Pricing Rule user record.
 *
 * @property int $id
 * @property ActiveQueryInterface $user
 * @property int $userId
 * @property ActiveQueryInterface $catalogPricingRule
 * @property int $catalogPricingRuleId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRuleUser extends ActiveRecord
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
            [['catalogPricingRuleId', 'userId'], 'unique', 'targetAttribute' => ['catalogPricingRuleId', 'userId']],
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCatalogPricingRule(): ActiveQueryInterface
    {
        return $this->hasOne(CatalogPricingRule::class, ['id' => 'catalogPricingRuleId']);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
