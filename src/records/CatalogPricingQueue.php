<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\helpers\Json;
use yii\db\ActiveQueryInterface;

/**
 * Catalog Pricing Queue record.
 *
 * @property int $id
 * @property int|null $storeId
 * @property string $type
 * @property array|null $ids
 * @property bool $reserved
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
class CatalogPricingQueue extends ActiveRecord
{
    /**
     * Row type for purchasable-ID-based catalog pricing work.
     */
    public const TYPE_PURCHASABLE = 'purchasable';

    /**
     * Row type for rule-ID-based (or full-regeneration) catalog pricing work.
     */
    public const TYPE_RULE = 'rule';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CATALOG_PRICING_QUEUE;
    }

    /**
     * Returns the decoded IDs array from the JSON column value.
     *
     * @return array|null
     */
    public function getIds(): ?array
    {
        $raw = $this->getAttribute('ids');

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = Json::decodeIfJson($raw);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Encodes the IDs array to JSON and stores it in the column.
     *
     * @param array|null $ids
     */
    public function setIds(?array $ids): void
    {
        $this->setAttribute('ids', $ids !== null ? Json::encode($ids) : null);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getStore(): ActiveQueryInterface
    {
        return $this->hasOne(Store::class, ['id' => 'storeId']);
    }
}
