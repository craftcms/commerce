<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\models\ShippingCategory;
use craft\commerce\records\ShippingCategory as ShippingCategoryRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\queue\jobs\ResaveElements;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Shipping category service.
 *
 * @property array|ShippingCategory[] $allShippingCategories all Shipping Categories
 * @property-read array $allShippingCategoriesAsList
 * @property null|ShippingCategory $defaultShippingCategory the default shipping category
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategories extends Component
{
    /**
     * @var ShippingCategory[]|null
     */
    private ?array $_allShippingCategories = null;

    /**
     * @var ShippingCategory[]|null
     */
    private ?array $_allShippingCategoriesWithTrashed = null;

    /**
     * Returns all Shipping Categories
     *
     * @param bool $withTrashed
     * @return array|ShippingCategory[]
     */
    public function getAllShippingCategories(bool $withTrashed = false): array
    {
        if ($this->_allShippingCategories === null) {
            $results = $this->_createShippingCategoryQuery(true)->all();

            $this->_allShippingCategories = [];
            $this->_allShippingCategoriesWithTrashed = [];
            foreach ($results as $result) {
                $shippingCategory = new ShippingCategory($result);

                if (!$shippingCategory->dateDeleted) {
                    $this->_allShippingCategories[] = $shippingCategory;
                }
                $this->_allShippingCategoriesWithTrashed[] = $shippingCategory;
            }
        }

        return $withTrashed ? $this->_allShippingCategoriesWithTrashed : $this->_allShippingCategories;
    }

    /**
     * Returns all Shipping category names, by ID.
     */
    public function getAllShippingCategoriesAsList(): array
    {
        $categories = $this->getAllShippingCategories();

        return ArrayHelper::map($categories, 'id', 'name');
    }

    /**
     * Get a shipping category by its ID.
     */
    public function getShippingCategoryById(int $shippingCategoryId): ?ShippingCategory
    {
        $categories = $this->getAllShippingCategories();

        return ArrayHelper::firstWhere($categories, 'id', $shippingCategoryId);
    }

    /**
     * Get a shipping category by its handle.
     *
     * @noinspection PhpUnused
     */
    public function getShippingCategoryByHandle(string $shippingCategoryHandle): ?ShippingCategory
    {
        $categories = $this->getAllShippingCategories();

        return ArrayHelper::firstWhere($categories, 'handle', $shippingCategoryHandle);
    }

    /**
     * Returns the default shipping category.
     *
     * @throws InvalidConfigException
     */
    public function getDefaultShippingCategory(): ShippingCategory
    {
        $categories = $this->getAllShippingCategories();

        $default = ArrayHelper::firstWhere($categories, 'default', true);

        if (!$default) {
            $default = ArrayHelper::firstValue($categories);
        }

        if (!$default) {
            throw new InvalidConfigException('Commerce must have at least one (default) shipping category set up.');
        }

        return $default;
    }

    /**
     * @param bool $runValidation should we validate this before saving.
     * @throws Exception
     * @throws \Exception
     */
    public function saveShippingCategory(ShippingCategory $shippingCategory, bool $runValidation = true): bool
    {
        if ($shippingCategory->id) {
            $record = ShippingCategoryRecord::findOne($shippingCategory->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No shipping category exists with the ID “{id}”',
                    ['id' => $shippingCategory->id]));
            }
        } else {
            $record = new ShippingCategoryRecord();
        }

        if ($runValidation && !$shippingCategory->validate()) {
            Craft::info('Shipping category not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $shippingCategory->name;
        $record->handle = $shippingCategory->handle;
        $record->description = $shippingCategory->description;
        $record->default = $shippingCategory->default;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $shippingCategory->id = $record->id;

        // If this was the default make all others not the default.
        if ($shippingCategory->default) {
            ShippingCategoryRecord::updateAll(['default' => false], ['not', ['id' => $record->id]]);
        }

        // Product type IDs this shipping category is available to
        $currentProductTypeIds = (new Query())
            ->select(['productTypeId'])
            ->from([Table::PRODUCTTYPES_SHIPPINGCATEGORIES])
            ->where(['shippingCategoryId' => $shippingCategory->id])
            ->column();

        // Newly set product types this shipping category is available to
        $newProductTypeIds = ArrayHelper::getColumn($shippingCategory->getProductTypes(), 'id');

        foreach ($currentProductTypeIds as $oldProductTypeId) {
            // If we are removing a product type for this shipping category the products of that type should be re-saved
            if (!in_array($oldProductTypeId, $newProductTypeIds, false)) {
                // Re-save all products that no longer have this shipping category available to them
                $this->_resaveProductsByProductTypeId($oldProductTypeId);
            }
        }

        foreach ($newProductTypeIds as $newProductTypeId) {
            // If we are adding a product type for this shipping category the products of that type should be re-saved
            if (!in_array($newProductTypeId, $currentProductTypeIds, false)) {
                // Re-save all products when assigning this shipping category available to them
                $this->_resaveProductsByProductTypeId($newProductTypeId);
            }
        }

        // Remove existing Categories <-> ProductType relationships
        Craft::$app->getDb()->createCommand()->delete(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, ['shippingCategoryId' => $shippingCategory->id])->execute();

        // Add back the new categories
        foreach ($shippingCategory->getProductTypes() as $productType) {
            $data = ['productTypeId' => (int)$productType->id, 'shippingCategoryId' => (int)$shippingCategory->id];
            Craft::$app->getDb()->createCommand()->insert(Table::PRODUCTTYPES_SHIPPINGCATEGORIES, $data)->execute();
        }

        // Clear Service cache
        $this->_allShippingCategories = null;

        return true;
    }

    /**
     * Re-save products by product type id
     */
    private function _resaveProductsByProductTypeId(int $productTypeId): void
    {
        Craft::$app->getQueue()->push(new ResaveElements([
            'elementType' => Product::class,
            'criteria' => [
                'typeId' => $productTypeId,
                'siteId' => '*',
                'unique' => true,
                'status' => null,
            ],
        ]));
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteShippingCategoryById(int $id): bool
    {
        $all = $this->getAllShippingCategories();
        if (count($all) === 0) {
            return false;
        }

        // Find the shipping category and check it isn't the default
        /** @var ShippingCategory $shippingCategory */
        $shippingCategory = ArrayHelper::firstWhere($all, fn(ShippingCategory $s) => $s->id == $id);

        if ($shippingCategory->default) {
            return false;
        }

        $affectedRows = Craft::$app->getDb()->createCommand()
            ->softDelete(\craft\commerce\db\Table::SHIPPINGCATEGORIES, ['id' => $id])
            ->execute();

        if ($affectedRows > 0) {
            return true;
        }

        // Clear cache
        $this->_allShippingCategories = null;

        return false;
    }

    /**
     * @param int $productTypeId
     * @return array
     * @throws InvalidConfigException
     */
    public function getShippingCategoriesByProductTypeId(int $productTypeId): array
    {
        $rows = $this->_createShippingCategoryQuery()
            ->innerJoin(Table::PRODUCTTYPES_SHIPPINGCATEGORIES . ' productTypeShippingCategories', '[[shippingCategories.id]] = [[productTypeShippingCategories.shippingCategoryId]]')
            ->andWhere(['productTypeShippingCategories.productTypeId' => $productTypeId])
            ->all();

        // Always need at least the default category
        if (empty($rows)) {
            try {
                $shippingCategory = $this->getDefaultShippingCategory();
            } catch (InvalidConfigException) {
                return [];
            }

            return [$shippingCategory->id => $shippingCategory];
        }

        $shippingCategories = [];

        foreach ($rows as $row) {
            $key = $row['id'];
            $shippingCategories[$key] = new ShippingCategory($row);
        }

        return $shippingCategories;
    }

    /**
     * Returns a Query object prepped for retrieving shipping categories.
     *
     * @param bool $withTrashed
     * @return Query
     */
    private function _createShippingCategoryQuery(bool $withTrashed = false): Query
    {
        $query = (new Query())
            ->select([
                'shippingCategories.dateCreated',
                'shippingCategories.dateDeleted',
                'shippingCategories.dateUpdated',
                'shippingCategories.default',
                'shippingCategories.description',
                'shippingCategories.handle',
                'shippingCategories.id',
                'shippingCategories.name',
            ])
            ->from([Table::SHIPPINGCATEGORIES . ' shippingCategories']);

        if (!$withTrashed) {
            $query->where(['dateDeleted' => null]);
        }

        return $query;
    }
}
