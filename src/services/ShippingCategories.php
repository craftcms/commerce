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
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingCategory as ShippingCategoryRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\queue\jobs\ResaveElements;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

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
     * @var Collection<ShippingCategory>[]|null
     */
    private ?array $_allShippingCategories = null;

    /**
     * Returns all Shipping Categories
     *
     * @param int|null $storeId
     * @param bool $withTrashed
     * @return Collection
     * @throws InvalidConfigException
     * @throws StoreNotFoundException
     */
    public function getAllShippingCategories(?int $storeId = null, bool $withTrashed = false): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allShippingCategories === null || !isset($this->_allShippingCategories[$storeId])) {
            $results = $this->_createShippingCategoryQuery(true)
                ->where(['storeId' => $storeId])
                ->all();

            if ($this->_allShippingCategories === null) {
                $this->_allShippingCategories = [];
            }

            foreach ($results as $result) {
                $shippingCategory = Craft::createObject([
                    'class' => ShippingCategory::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allShippingCategories[$shippingCategory->storeId])) {
                    $this->_allShippingCategories[$shippingCategory->storeId] = collect();
                }

                $this->_allShippingCategories[$shippingCategory->storeId]->push($shippingCategory);
            }
        }

        if (!isset($this->_allShippingCategories[$storeId])) {
            return collect();
        }

        return $this->_allShippingCategories[$storeId]->filter(fn(ShippingCategory $sc) => (!$withTrashed && $sc->dateDeleted === null) || $withTrashed);
    }

    /**
     * Returns all Shipping category names, by ID.
     *
     * @throws InvalidConfigException
     */
    public function getAllShippingCategoriesAsList(?int $storeId = null): array
    {
        $categories = $this->getAllShippingCategories($storeId);

        return $categories->mapWithKeys(function(ShippingCategory $category) {
            return [$category->id => $category->name];
        })->all();
    }

    /**
     * Get a shipping category by its ID.
     *
     * @param int $shippingCategoryId
     * @param int|null $storeId
     * @return ShippingCategory|null
     * @throws InvalidConfigException
     */
    public function getShippingCategoryById(int $shippingCategoryId, ?int $storeId = null): ?ShippingCategory
    {
        return $this->getAllShippingCategories($storeId)->firstWhere('id', $shippingCategoryId);
    }

    /**
     * Get a shipping category by its handle.
     *
     * @noinspection PhpUnused
     * @throws InvalidConfigException
     */
    public function getShippingCategoryByHandle(string $shippingCategoryHandle, ?int $storeId = null): ?ShippingCategory
    {
        return $this->getAllShippingCategories($storeId)->firstWhere('handle', $shippingCategoryHandle);
    }

    /**
     * Returns the default shipping category.
     *
     * @throws InvalidConfigException
     */
    public function getDefaultShippingCategory(int $storeId): ShippingCategory
    {
        $categories = $this->getAllShippingCategories($storeId);

        $default = $categories->firstWhere('default', true);

        if (!$default) {
            $default = $categories->first();
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
        $record->storeId = $shippingCategory->storeId;
        $record->handle = $shippingCategory->handle;
        $record->description = $shippingCategory->description;
        $record->default = $shippingCategory->default;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $shippingCategory->id = $record->id;

        // If this was the default make all others not the default.
        if ($shippingCategory->default) {
            $condition = [
                'and',
                ['storeId' => $record->storeId],
                ['not', ['id' => $record->id]],
            ];
            ShippingCategoryRecord::updateAll(['default' => false], $condition);
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
        /** @var ShippingCategoryRecord|SoftDeleteBehavior|null $shippingCategory */
        $shippingCategory = ShippingCategoryRecord::findOne($id);

        if ($shippingCategory === null || $shippingCategory->default) {
            return false;
        }

        if ($shippingCategory->softDelete()) {
            $this->_allShippingCategories = null;
            return true;
        }

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
                // @TODO fix this properly
                $shippingCategory = $this->getAllShippingCategories()->firstWhere('default', true);
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
     * @return void
     * @since 5.0.0
     */
    public function clearCaches(): void
    {
        $this->_allShippingCategories = null;
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
                'shippingCategories.storeId',
            ])
            ->from([Table::SHIPPINGCATEGORIES . ' shippingCategories']);

        if (!$withTrashed) {
            $query->where(['dateDeleted' => null]);
        }

        return $query;
    }
}
