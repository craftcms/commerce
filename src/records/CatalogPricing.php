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

/**
 * Catalog Pricing record.
 *
 * @property int $id
 * @property float $price
 * @property int $purchasableId
 * @property int $storeId
 * @property int $catalogPricingRuleId
 * @property int $uid
 * @property \DateTime $dateFrom
 * @property \DateTime $dateTo
 * @property bool $isPromotionalPrice
 * @property bool $hasUpdatePending
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricing extends ActiveRecord
{
    use StoreRecordTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CATALOG_PRICING;
    }
}
