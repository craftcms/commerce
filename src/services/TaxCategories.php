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
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

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
    private ?array $_allTaxCategories = null;

    /**
     * @var TaxCategory[]|null
     */
    private ?array $_allTaxCategoriesWithTrashed = null;

    /**
     * Returns all Tax Categories
     * @param bool $withTrashed
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(bool $withTrashed = false): array
    {
        if ($this->_allTaxCategories === null || $this->_allTaxCategoriesWithTrashed === null) {
            $results = $this->_createTaxCategoryQuery(true)->all();

            $this->_allTaxCategories = [];
            foreach ($results as $result) {
                $taxCategory = new TaxCategory($result);

                if (!$taxCategory->dateDeleted) {
                    $this->_allTaxCategories[] = $taxCategory;
                }
                $this->_allTaxCategoriesWithTrashed[] = $taxCategory;
            }
        }

        return $withTrashed ? $this->_allTaxCategoriesWithTrashed : $this->_allTaxCategories;
    }

    /**
     * Get a tax category by its ID.
     */
    public function getTaxCategoryById(int $taxCategoryId): ?TaxCategory
    {
        $categories = $this->getAllTaxCategories();

        return ArrayHelper::firstWhere($categories, 'id', $taxCategoryId);
    }

    /**
     * Get a tax category by its handle.
     *
     * @noinspection PhpUnused
     */
    public function getTaxCategoryByHandle(string $taxCategoryHandle): ?TaxCategory
    {
        $categories = $this->getAllTaxCategories();

        return ArrayHelper::firstWhere($categories, 'handle', $taxCategoryHandle);
    }

    /**
     * Returns all Tax category names, indexed by ID.
     */
    public function getAllTaxCategoriesAsList(): array
    {
        $categories = $this->getAllTaxCategories();

        return ArrayHelper::map($categories, 'id', 'name');
    }

    /**
     * Get the default tax category
     *
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
     * @param bool $runValidation should we validate this state before saving.
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
     * @param int $id
     * @return bool
     * @throws StaleObjectException
     */
    public function deleteTaxCategoryById(int $id): bool
    {
        /** @var TaxCategoryRecord|SoftDeleteBehavior|null $taxCategory */
        $taxCategory = TaxCategoryRecord::findOne($id);

        if ($taxCategory === null || $taxCategory->default) {
            return false;
        }

        if ($taxCategory->softDelete()) {
            $this->_allTaxCategories = null;
            return true;
        }

        return false;
    }

    /**
     * @param int $productTypeId
     * @return array
     */
    public function getTaxCategoriesByProductTypeId(int $productTypeId): array
    {
        $rows = $this->_createTaxCategoryQuery()
            ->innerJoin(Table::PRODUCTTYPES_TAXCATEGORIES . ' productTypeTaxCategories', '[[taxCategories.id]] = [[productTypeTaxCategories.taxCategoryId]]')
            ->andWhere(['productTypeTaxCategories.productTypeId' => $productTypeId])
            ->all();

        if (empty($rows)) {
            try {
                $taxCategory = $this->getDefaultTaxCategory();
            } catch (InvalidConfigException) {
                return [];
            }

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
     * Returns a Query object prepped for retrieving tax categories.
     */
    private function _createTaxCategoryQuery(bool $withTrashed = false): Query
    {
        $query = (new Query())
            ->select([
                'taxCategories.dateCreated',
                'taxCategories.dateDeleted',
                'taxCategories.dateUpdated',
                'taxCategories.default',
                'taxCategories.description',
                'taxCategories.handle',
                'taxCategories.id',
                'taxCategories.name',
            ])
            ->from([Table::TAXCATEGORIES . ' taxCategories']);

        if (!$withTrashed) {
            $query->where(['dateDeleted' => null]);
        }

        return $query;
    }
}
