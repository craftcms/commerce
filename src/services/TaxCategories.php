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
use craft\commerce\records\TaxCategory as TaxCategoryRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\queue\jobs\ResaveElements;
use yii\base\BaseObject;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

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
     * @var TaxCategory[]|null
     */
    private $_allTaxCategories = null;

    /**
     * Returns all Tax Categories
     *
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(): array
    {
        if ($this->_allTaxCategories === null) {
            $results = $this->_createTaxCategoryQuery()->all();

            $this->_allTaxCategories = [];
            foreach ($results as $result) {
                $taxCategory = new TaxCategory($result);
                $this->_allTaxCategories[] = $taxCategory;
            }
        }

        return $this->_allTaxCategories;
    }

    /**
     * Get a tax category by its ID.
     *
     * @param int $taxCategoryId
     * @return TaxCategory|null
     */
    public function getTaxCategoryById($taxCategoryId): ?TaxCategory
    {
        $categories = $this->getAllTaxCategories();

        return ArrayHelper::firstWhere($categories, 'id', $taxCategoryId);
    }

    /**
     * Get a tax category by its handle.
     *
     * @param string $taxCategoryHandle
     * @return TaxCategory|null
     */
    public function getTaxCategoryByHandle($taxCategoryHandle): ?TaxCategory
    {
        $categories = $this->getAllTaxCategories();

        return ArrayHelper::firstWhere($categories, 'handle', $taxCategoryHandle);
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
     * @return TaxCategory
     * @throws InvalidConfigException
     */
    public function getDefaultTaxCategory(): TaxCategory
    {
        $categories = $this->getAllTaxCategories();

        $default = ArrayHelper::firstWhere($categories, 'default', true);

        if (!$default) {
            $default = ArrayHelper::firstValue($categories);
        }

        if (!$default) {
            throw new InvalidConfigException('Commerce must have at least one (default) tax category set up.');
        }

        return $default;
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
        if ($taxCategory->id) {
            $record = TaxCategoryRecord::findOne($taxCategory->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No tax category exists with the ID “{id}”',
                    ['id' => $taxCategory->id]));
            }
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
                $this->_resaveProductsByProductTypeId($oldProductTypeId);
            }
        }

        foreach ($newProductTypeIds as $newProductTypeId) {
            // If we are adding a product type for this tax category the products of that type should be re-saved
            if (!in_array($newProductTypeId, $currentProductTypeIds, false)) {
                // Re-save all products when assigning this tax category available to them
                $this->_resaveProductsByProductTypeId($newProductTypeId);
            }
        }

        // Remove existing Categories <-> ProductType relationships
        Craft::$app->getDb()->createCommand()->delete(Table::PRODUCTTYPES_TAXCATEGORIES, ['taxCategoryId' => $record->id])->execute();

        foreach ($taxCategory->getProductTypes() as $productType) {
            $data = ['productTypeId' => (int)$productType->id, 'taxCategoryId' => $taxCategory->id];
            Craft::$app->getDb()->createCommand()->insert(Table::PRODUCTTYPES_TAXCATEGORIES, $data)->execute();
        }

        // Clear Service cache
        $this->_allTaxCategories = null;

        return true;
    }

    /**
     * Re-save products by product type id
     *
     * @param int $productTypeId
     */
    private function _resaveProductsByProductTypeId(int $productTypeId)
    {
        Craft::$app->getQueue()->push(new ResaveElements([
            'elementType' => Product::class,
            'criteria' => [
                'typeId' => $productTypeId,
                'siteId' => '*',
                'unique' => true,
                'status' => null,
                'enabledForSite' => false,
            ]
        ]));
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
                'taxCategories.default',
                'taxCategories.dateCreated',
                'taxCategories.dateUpdated',
            ])
            ->from([Table::TAXCATEGORIES . ' taxCategories']);
    }
}
