<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\Category;
use craft\records\UserGroup;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Sale record.
 *
 * @property bool $allCategories
 * @property bool $allGroups
 * @property bool $allPurchasables
 * @property DateTime $dateFrom
 * @property DateTime $dateTo
 * @property string $description
 * @property float $applyAmount
 * @property bool $ignorePrevious
 * @property bool $stopProcessing
 * @property string $apply
 * @property string $categoryRelationshipType
 * @property bool $enabled
 * @property UserGroup[] $groups
 * @property int $id
 * @property string $name
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Sale extends ActiveRecord
{
    const APPLY_BY_PERCENT = 'byPercent';
    const APPLY_BY_FLAT = 'byFlat';
    const APPLY_TO_PERCENT = 'toPercent';
    const APPLY_TO_FLAT = 'toFlat';

    const CATEGORY_RELATIONSHIP_TYPE_SOURCE = 'sourceElement';
    const CATEGORY_RELATIONSHIP_TYPE_TARGET = 'targetElement';
    const CATEGORY_RELATIONSHIP_TYPE_BOTH = 'element';


    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SALES;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGroups(): ActiveQueryInterface
    {
        return $this->hasMany(UserGroup::class, ['id' => 'userGroupId'])->viaTable(Table::SALE_USERGROUP, ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasables(): ActiveQueryInterface
    {
        return $this->hasMany(Purchasable::class, ['id' => 'purchasableId'])->viaTable(Table::SALE_PURCHASABLES, ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCategories(): ActiveQueryInterface
    {
        return $this->hasMany(Category::class, ['id' => 'categoryId'])->viaTable(Table::SALE_CATEGORIES, ['saleId' => 'id']);
    }
}
