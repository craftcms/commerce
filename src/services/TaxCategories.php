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
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\TaxCategory as TaxCategoryRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\queue\jobs\ResaveElements;
use yii\base\Component;
use yii\base\Exception;

/**
 * Tax category service.
 *
 * @property TaxCategory[]|array $allTaxCategories all Tax Categories
 * @property array $allTaxCategoriesAsList
 * @property TaxCategory|null $defaultTaxCategory the default tax category
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxCategories extends Component
{
    /**
     * @var bool
     */
    private $_fetchedAllTaxCategories = false;

    /**
     * @var TaxCategory[]
     */
    private $_taxCategoriesById = [];

    /**
     * @var TaxCategory[]
     */
    private $_taxCategoriesByHandle = [];

    /**
     * @var TaxCategory
     */
    private $_defaultTaxCategory;


    /**
     * Returns all Tax Categories
     *
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(): array
    {
        if (!$this->_fetchedAllTaxCategories) {
            $results = $this->_createTaxCategoryQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeTaxCategory(new TaxCategory($result));
            }

            $this->_fetchedAllTaxCategories = true;
        }

        return $this->_taxCategoriesById;
    }

    /**
     * Get a tax category by its ID.
     *
     * @param int $taxCategoryId
     * @return TaxCategory|null
     */
    public function getTaxCategoryById($taxCategoryId)
    {
        if (isset($this->_taxCategoriesById[$taxCategoryId])) {
            return $this->_taxCategoriesById[$taxCategoryId];
        }

        if ($this->_fetchedAllTaxCategories) {
            return null;
        }

        $result = $this->_createTaxCategoryQuery()
            ->where(['id' => $taxCategoryId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeTaxCategory(new TaxCategory($result));

        return $this->_taxCategoriesById[$taxCategoryId];
    }

    /**
     * Get a tax category by its handle.
     *
     * @param int $taxCategoryHandle
     * @return TaxCategory|null
     */
    public function getTaxCategoryByHandle($taxCategoryHandle)
    {
        if (isset($this->_taxCategoriesByHandle[$taxCategoryHandle])) {
            return $this->_taxCategoriesByHandle[$taxCategoryHandle];
        }

        if ($this->_fetchedAllTaxCategories) {
            return null;
        }

        $result = $this->_createTaxCategoryQuery()
            ->where(['handle' => $taxCategoryHandle])
            ->one();

        if (!$result) {
            return null;
        }

        $taxCategory = new TaxCategory($result);
        $this->_memoizeTaxCategory($taxCategory);

        return $this->_taxCategoriesByHandle[$taxCategoryHandle];
    }

    /**
     * Returns all Tax category names, indexed by ID.
     *
     * @return array
     */
    public function getAllTaxCategoriesAsList(): array
    {
        $categories = $this->getAllTaxCategories();

        return ArrayHelper::map($categories, 'id', 'name');
    }

    /**
     * Get the default tax category
     *
     * @return TaxCategory|null
     */
    public function getDefaultTaxCategory()
    {
        if ($this->_defaultTaxCategory !== null) {
            return $this->_defaultTaxCategory;
        }

        $result = $this->_createTaxCategoryQuery()
            ->where(['default' => true])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_defaultTaxCategory = new TaxCategory($result);
    }

    /**
     * Save a tax category.
     *
     * @param TaxCategory $taxCategory
     * @param bool $runValidation should we validate this state before saving.
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveTaxCategory(TaxCategory $taxCategory, bool $runValidation = true): bool
    {
        $oldHandle = null;

        if ($taxCategory->id) {
            $record = TaxCategoryRecord::findOne($taxCategory->id);

            if (!$record) {
                throw new Exception(Plugin::t( 'No tax category exists with the ID “{id}”',
                    ['id' => $taxCategory->id]));
            }

            $oldHandle = $record->handle;
        } else {
            $record = new TaxCategoryRecord();
        }

        if ($runValidation && !$taxCategory->validate()) {
            Craft::info('Tax category not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $taxCategory->name;
        $record->handle = $taxCategory->handle;
        $record->description = $taxCategory->description;
        $record->default = $taxCategory->default;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $taxCategory->id = $record->id;

        // If this was the default make all others not the default.
        if ($taxCategory->default) {
            TaxCategoryRecord::updateAll(['default' => false], ['not', ['id' => $record->id]]);
        }

        // Product type IDs this tax category is available to
        $currentProductTypeIds = (new Query())
            ->select(['productTypeId'])
            ->from([Table::PRODUCTTYPES_TAXCATEGORIES])
            ->where(['taxCategoryId' => $taxCategory->id])
            ->column();

        // Newly set product types this tax category is available to
        $newProductTypeIds = ArrayHelper::getColumn($taxCategory->getProductTypes(), 'id');

        foreach ($currentProductTypeIds as $oldProductTypeId) {
            // If we are removing a product type for this tax category the products of that type should be re-saved
            if (!in_array($oldProductTypeId, $newProductTypeIds, false)) {
                // Re-save all products that no longer have this tax category available to them
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
        Craft::$app->getDb()->createCommand()->delete(Table::PRODUCTTYPES_TAXCATEGORIES, ['taxCategoryId' => $record->id])->execute();

        foreach ($taxCategory->getProductTypes() as $productType) {
            $data = ['productTypeId' => (int)$productType->id, 'taxCategoryId' => $taxCategory->id];
            Craft::$app->getDb()->createCommand()->insert(Table::PRODUCTTYPES_TAXCATEGORIES, $data)->execute();
        }

        // Update Service cache
        $this->_memoizeTaxCategory($taxCategory);

        if (null !== $oldHandle && $taxCategory->handle != $oldHandle) {
            unset($this->_taxCategoriesByHandle[$oldHandle]);
        }

        return true;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteTaxCategoryById($id): bool
    {
        $all = $this->getAllTaxCategories();

        // Not the last one.
        if (count($all) === 1) {
            return false;
        }

        $record = TaxCategoryRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * @param $productTypeId
     * @return array
     */
    public function getTaxCategoriesByProductTypeId($productTypeId): array
    {
        $rows = $this->_createTaxCategoryQuery()
            ->innerJoin(Table::PRODUCTTYPES_TAXCATEGORIES . ' productTypeTaxCategories', '[[taxCategories.id]] = [[productTypeTaxCategories.taxCategoryId]]')
            ->where(['productTypeTaxCategories.productTypeId' => $productTypeId])
            ->all();

        if (empty($rows)) {
            $category = $this->getDefaultTaxCategory();

            if (!$category) {
                return [];
            }

            $taxCategory = $this->getDefaultTaxCategory();

            return [$taxCategory->id => $taxCategory];
        }

        $taxCategories = [];

        foreach ($rows as $row) {
            $key = $row['id'];
            $taxCategories[$key] = new TaxCategory($row);
        }

        return $taxCategories;
    }


    /**
     * Memoize a tax category model by its ID and handle.
     *
     * @param TaxCategory $taxCategory
     */
    private function _memoizeTaxCategory(TaxCategory $taxCategory)
    {
        $this->_taxCategoriesById[$taxCategory->id] = $taxCategory;
        $this->_taxCategoriesByHandle[$taxCategory->handle] = $taxCategory;
    }

    /**
     * Returns a Query object prepped for retrieving tax categories.
     *
     * @return Query
     */
    private function _createTaxCategoryQuery(): Query
    {
        return (new Query())
            ->select([
                'taxCategories.id',
                'taxCategories.name',
                'taxCategories.handle',
                'taxCategories.description',
                'taxCategories.default'
            ])
            ->from([Table::TAXCATEGORIES . ' taxCategories']);
    }
}
