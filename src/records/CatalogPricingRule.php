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
use DateTime;
use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;

/**
 * Catalog Pricing Rule record.
 *
 * @property string $customerCondition
 * @property string $purchasableCondition
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property string $description
 * @property float $applyAmount
 * @property string $apply
 * @property bool $enabled
 * @property bool $isPromotionalPrice
 * @property int $id
 * @property-read ActiveQueryInterface $users
 * @property string $name
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRule extends ActiveRecord
{
    public const APPLY_BY_PERCENT = 'byPercent';
    public const APPLY_BY_FLAT = 'byFlat';
    public const APPLY_TO_PERCENT = 'toPercent';
    public const APPLY_TO_FLAT = 'toFlat';

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
