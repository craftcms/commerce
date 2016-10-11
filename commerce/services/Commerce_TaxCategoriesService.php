<?php
namespace Craft;

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
class Commerce_TaxCategoriesService extends BaseApplicationComponent
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
     * @return Commerce_TaxCategoryModel|null
     */
    public function getTaxCategoryById($taxCategoryId)
    {
        if(!$this->_fetchedAllTaxCategories &&
            (!isset($this->_taxCategoriesById) || !array_key_exists($taxCategoryId, $this->_taxCategoriesById))
        )
        {
            $result = Commerce_TaxCategoryRecord::model()->findById($taxCategoryId);

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
     * @param int $taxCategoryHandle
     *
     * @return Commerce_TaxCategoryModel|null
     */
    public function getTaxCategoryByHandle($taxCategoryHandle)
    {
        if(!$this->_fetchedAllTaxCategories &&
            (!isset($this->_taxCategoriesByHandle) || !array_key_exists($taxCategoryHandle, $this->_taxCategoriesByHandle))
        )
        {
            $result = Commerce_TaxCategoryRecord::model()->findByAttributes([
                'handle' => $taxCategoryHandle
            ]);

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
     * @return int|null
     */
    public function getDefaultTaxCategory()
    {
        foreach($this->getAllTaxCategories() as $taxCategory){
            if ($taxCategory->default) {
                return $taxCategory;
            }
        }

        return null;
    }

    /**
     * @param Commerce_TaxCategoryModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveTaxCategory(Commerce_TaxCategoryModel $model)
    {
        if ($model->id) {
            $record = Commerce_TaxCategoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }

            $oldHandle = $record->handle;
        } else {
            $record = new Commerce_TaxCategoryRecord();
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
                Commerce_TaxCategoryRecord::model()->updateAll(['default' => 0],
                    'id != ?', [$record->id]);
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
     * @return bool
     */
    public function deleteTaxCategoryById($id)
    {
        $all = $this->getAllTaxCategories();
        if (count($all) == 1) {
            return false;
        }

        return (bool)Commerce_TaxCategoryRecord::model()->deleteByPk($id);
    }

    /**
     * Returns all Tax Categories
     *
     * @param string|null $indexBy
     * @return Commerce_TaxCategoryModel[]
     */
    public function getAllTaxCategories($indexBy = null)
    {
        if (!$this->_fetchedAllTaxCategories) {
            $results = Commerce_TaxCategoryRecord::model()->findAll();

            foreach($results as $result){
                $this->_populateTaxCategory($result);
            }

            $this->_fetchedAllTaxCategories = true;
        }

        if ($indexBy == 'id') {
            $taxCategories = array_filter($this->_taxCategoriesById);
        } else if (!$indexBy) {
            $taxCategories = array_values(array_filter($this->_taxCategoriesById));
        } else {
            $taxCategories = array();

            foreach (array_filter($this->_taxCategoriesById) as $taxCategory) {
                $taxCategories[$taxCategory->$indexBy] = $taxCategory;
            }
        }

        return $taxCategories;
    }

    /**
     * Populates, memoizes, and returns a tax category model based on a given set of values or model/record.
     *
     * @param mixed $values
     *
     * @return Commerce_TaxCategoryModel
     */
    private function _populateTaxCategory($values)
    {
        $taxCategory = Commerce_TaxCategoryModel::populateModel($values);

        if ($taxCategory->id) {
            $this->_memoizeTaxCategory($taxCategory);
        }

        return $taxCategory;
    }

    /**
     * Memoizes a tax category model by its ID and handle.
     *
     * @param Commerce_TaxCategoryModel $taxCategory
     *
     * @return void
     */
    private function _memoizeTaxCategory(Commerce_TaxCategoryModel $taxCategory)
    {
        $this->_taxCategoriesById[$taxCategory->id] = $taxCategory;
        $this->_taxCategoriesByHandle[$taxCategory->handle] = $taxCategory;
    }
}
