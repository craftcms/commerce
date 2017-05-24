<?php

namespace craft\commerce\services;

use craft\commerce\models\TaxCategory;
use craft\commerce\records\TaxCategory as TaxCategoryRecord;
use yii\base\Component;

/**
 * Tax category service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class TaxCategories extends Component
{

    /**
     * @var bool
     */
    private $_fetchedAllTaxCategories = false;

    /**
     * @var
     */
    private $_taxCategoriesById;

    /**
     * @var
     */
    private $_taxCategoriesByHandle;

    /**
     * @param int $taxCategoryId
     *
     * @return TaxCategory|null
     */
    public function getTaxCategoryById($taxCategoryId)
    {
        if (!$this->_fetchedAllTaxCategories &&
            (!isset($this->_taxCategoriesById) || !array_key_exists($taxCategoryId, $this->_taxCategoriesById))
        ) {
            $result = TaxCategoryRecord::findOne($taxCategoryId);

            if ($result) {
                $taxCategory = $this->_populateTaxCategory($result);
            } else {
                // Remember that this ID doesn't exist
                $this->_taxCategoriesById[$taxCategoryId] = null;
            }
        }

        return $this->_taxCategoriesById[$taxCategoryId];
    }

    /**
     * Populates, memoizes, and returns a tax category model based on a given set of values or model/record.
     *
     * @param mixed $values
     *
     * @return TaxCategory
     */
    private function _populateTaxCategory($values)
    {
        $taxCategory = TaxCategory::populateModel($values);

        if ($taxCategory->id) {
            $this->_memoizeTaxCategory($taxCategory);
        }

        return $taxCategory;
    }

    /**
     * Memoizes a tax category model by its ID and handle.
     *
     * @param TaxCategory $taxCategory
     *
     * @return void
     */
    private function _memoizeTaxCategory(TaxCategory $taxCategory)
    {
        $this->_taxCategoriesById[$taxCategory->id] = $taxCategory;
        $this->_taxCategoriesByHandle[$taxCategory->handle] = $taxCategory;
    }

    /**
     * @param int $taxCategoryHandle
     *
     * @return TaxCategory|null
     */
    public function getTaxCategoryByHandle($taxCategoryHandle)
    {
        if (!$this->_fetchedAllTaxCategories &&
            (!isset($this->_taxCategoriesByHandle) || !array_key_exists($taxCategoryHandle, $this->_taxCategoriesByHandle))
        ) {
            $result = TaxCategoryRecord::find()->where([
                'handle' => $taxCategoryHandle
            ])->one();

            if ($result) {
                $taxCategory = $this->_populateTaxCategory($result);
            } else {
                // Remember that this handle doesn't exist
                $this->_taxCategoriesByHandle[$taxCategoryHandle] = null;
            }
        }

        return $this->_taxCategoriesByHandle[$taxCategoryHandle];
    }

    /**
     * Default tax category
     *
     * @return TaxCategory|null
     */
    public function getDefaultTaxCategory()
    {
        foreach ($this->getAllTaxCategories() as $taxCategory) {
            if ($taxCategory->default) {
                return $taxCategory;
            }
        }

        return null;
    }

    /**
     * Returns all Tax Categories
     *
     * @param string|null $indexBy
     *
     * @return TaxCategory[]
     */
    public function getAllTaxCategories($indexBy = null)
    {
        if (!$this->_fetchedAllTaxCategories) {
            $results = TaxCategoryRecord::find()->all();

            foreach ($results as $result) {
                $this->_populateTaxCategory($result);
            }

            $this->_fetchedAllTaxCategories = true;
        }

        if ($indexBy == 'id') {
            $taxCategories = array_filter($this->_taxCategoriesById);
        } else if (!$indexBy) {
            $taxCategories = array_values(array_filter($this->_taxCategoriesById));
        } else {
            $taxCategories = [];

            foreach (array_filter($this->_taxCategoriesById) as $taxCategory) {
                $taxCategories[$taxCategory->$indexBy] = $taxCategory;
            }
        }

        return $taxCategories;
    }

    /**
     * @param TaxCategory $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveTaxCategory(TaxCategory $model)
    {
        if ($model->id) {
            $record = TaxCategoryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No tax category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }

            $oldHandle = $record->handle;
        } else {
            $record = new TaxCategoryRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->description = $model->description;
        $record->default = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            // If this was the default make all others not the default.
            if ($model->default) {
                TaxCategoryRecord::updateAll(['default' => 0], 'id != ?', [$record->id]);
            }

            // Update Service cache
            $this->_memoizeTaxCategory($model);

            if (isset($oldHandle) && $model->handle != $oldHandle) {
                unset($this->_taxCategoriesByHandle[$oldHandle]);
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteTaxCategoryById($id)
    {
        $all = $this->getAllTaxCategories();
        if (count($all) == 1) {
            return false;
        }

        $record = TaxCategoryRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }
    }
}
