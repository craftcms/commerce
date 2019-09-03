<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\ShippingCategory;
use craft\commerce\records\ShippingCategory as ShippingCategoryRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\queue\jobs\ResaveElements;
use yii\base\Component;
use yii\base\Exception;

/**
 * Shipping category service.
 *
 * @property array|ShippingCategory[] $allShippingCategories all Shipping Categories
 * @property null|ShippingCategory $defaultShippingCategory the default shipping category
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategories extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllShippingCategories = false;

    /**
     * @var ShippingCategory[]
     */
    private $_shippingCategoriesById = [];

    /**
     * @var ShippingCategory[]
     */
    private $_shippingCategoriesByHandle;

    /**
     * @var ShippingCategory
     */
    private $_defaultShippingCategory;

    // Public Methods
    // =========================================================================

    /**
     * Returns all Shipping Categories
     *
     * @return ShippingCategory[]
     */
    public function getAllShippingCategories(): array
    {
        if (!$this->_fetchedAllShippingCategories) {
            $results = $this->_createShippingCategoryQuery()->all();

            foreach ($results as $result) {
                $shippingCategory = new ShippingCategory($result);
                $this->_memoizeShippingCategory($shippingCategory);
            }

            $this->_fetchedAllShippingCategories = true;
        }

        return $this->_shippingCategoriesById;
    }

    /**
     * Get a shipping category by its ID.
     *
     * @param int $shippingCategoryId
     * @return ShippingCategory|null
     */
    public function getShippingCategoryById(int $shippingCategoryId)
    {
        if (isset($this->_shippingCategoriesById[$shippingCategoryId])) {
            return $this->_shippingCategoriesById[$shippingCategoryId];
        }

        if ($this->_fetchedAllShippingCategories) {
            return null;
        }

        $result = $this->_createShippingCategoryQuery()
            ->where(['id' => $shippingCategoryId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeShippingCategory(new ShippingCategory($result));

        return $this->_shippingCategoriesById[$shippingCategoryId];
    }

    /**
     * Get a shipping category by its handle.
     *
     * @param string $shippingCategoryHandle
     * @return ShippingCategory|null
     */
    public function getShippingCategoryByHandle(string $shippingCategoryHandle)
    {
        if (isset($this->_shippingCategoriesByHandle[$shippingCategoryHandle])) {
            return $this->_shippingCategoriesByHandle[$shippingCategoryHandle];
        }

        if ($this->_fetchedAllShippingCategories) {
            return null;
        }

        $result = $this->_createShippingCategoryQuery()
            ->where(['handle' => $shippingCategoryHandle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeShippingCategory(new ShippingCategory($result));

        return $this->_shippingCategoriesByHandle[$shippingCategoryHandle];
    }

    /**
     * Returns the default shipping category.
     *
     * @return ShippingCategory|null
     */
    public function getDefaultShippingCategory()
    {
        if ($this->_defaultShippingCategory !== null) {
            return $this->_defaultShippingCategory;
        }

        $row = $this->_createShippingCategoryQuery()
            ->where(['default' => true])
            ->one();

        if (!$row) {
            return null;
        }

        return $this->_defaultShippingCategory = new ShippingCategory($row);
    }

    /**
     * @param ShippingCategory $shippingCategory
     * @param bool $runValidation should we validate this before saving.
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveShippingCategory(ShippingCategory $shippingCategory, bool $runValidation = true): bool
    {
        $oldHandle = null;

        if ($shippingCategory->id) {
            $record = ShippingCategoryRecord::findOne($shippingCategory->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No shipping category exists with the ID “{id}”',
                    ['id' => $shippingCategory->id]));
            }

            $oldHandle = $record->handle;
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
            ->from(['{{%commerce_producttypes_shippingcategories}}'])
            ->where(['shippingCategoryId' => $shippingCategory->id])
            ->column();

        // Newly set product types this shipping category is available to
        $newProductTypeIds = ArrayHelper::getColumn($shippingCategory->getProductTypes(), 'id');

        foreach ($currentProductTypeIds as $oldProductTypeId) {
            // If we are removing a product type for this shipping category the products of that type should be re-saved
            if (!in_array($oldProductTypeId, $newProductTypeIds, false)) {
                // Re-save all products that no longer have this shipping category available to them
                Craft::$app->getQueue()->push(new ResaveElements([
                    'elementType' => Product::class,
                    'criteria' => [
                        'typeId' => $oldProductTypeId,
                        'siteId' => '*',
                        'unique' => true,
                        'status' => null,
                        'enabledForSite' => false,
                    ]
                ]));
            }
        }

        // Remove existing Categories <-> ProductType relationships
        Craft::$app->getDb()->createCommand()->delete('{{%commerce_producttypes_shippingcategories}}', ['shippingCategoryId' => $shippingCategory->id])->execute();

        // Add back the new categories
        foreach ($shippingCategory->getProductTypes() as $productType) {
            $data = ['productTypeId' => (int)$productType->id, 'shippingCategoryId' => (int)$shippingCategory->id];
            Craft::$app->getDb()->createCommand()->insert('{{%commerce_producttypes_shippingcategories}}', $data)->execute();
        }

        // Update Service cache
        $this->_memoizeShippingCategory($shippingCategory);

        if (null !== $oldHandle && $shippingCategory->handle != $oldHandle) {
            unset($this->_shippingCategoriesByHandle[$oldHandle]);
        }


        return true;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteShippingCategoryById($id): bool
    {
        $all = $this->getAllShippingCategories();
        if (count($all) === 1) {
            return false;
        }

        $record = ShippingCategoryRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * @param $productTypeId
     * @return array
     */
    public function getShippingCategoriesByProductTypeId($productTypeId): array
    {
        $rows = $this->_createShippingCategoryQuery()
            ->innerJoin('{{%commerce_producttypes_shippingcategories}} productTypeShippingCategories', '[[shippingCategories.id]] = [[productTypeShippingCategories.shippingCategoryId]]')
            ->where(['productTypeShippingCategories.productTypeId' => $productTypeId])
            ->all();

        // Always need at least the default category
        if (empty($rows)) {
            $category = $this->getDefaultShippingCategory();

            if (!$category) {
                return [];
            }

            $shippingCategory = $this->getDefaultShippingCategory();

            return [$shippingCategory->id => $shippingCategory];
        }

        $shippingCategories = [];

        foreach ($rows as $row) {
            $key = $row['id'];
            $shippingCategories[$key] = new ShippingCategory($row);
        }

        return $shippingCategories;
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize a shipping category model by its ID and handle.
     *
     * @param ShippingCategory $shippingCategory
     */
    private function _memoizeShippingCategory(ShippingCategory $shippingCategory)
    {
        $this->_shippingCategoriesById[$shippingCategory->id] = $shippingCategory;
        $this->_shippingCategoriesByHandle[$shippingCategory->handle] = $shippingCategory;
    }

    /**
     * Returns a Query object prepped for retrieving shipping categories.
     *
     * @return Query
     */
    private function _createShippingCategoryQuery(): Query
    {
        return (new Query())
            ->select([
                'shippingCategories.id',
                'shippingCategories.name',
                'shippingCategories.handle',
                'shippingCategories.description',
                'shippingCategories.default'
            ])
            ->from(['{{%commerce_shippingcategories}} shippingCategories']);
    }
}
