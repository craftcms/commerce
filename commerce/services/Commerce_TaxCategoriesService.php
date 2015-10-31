<?php
namespace Craft;

/**
 * Tax category service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
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
     * @param int $taxCategoryId
     *
     * @return Commerce_TaxCategoryModel|null
     */
    public function getById($taxCategoryId)
    {
        if(!$this->_fetchedAllTaxCategories &&
            (!isset($this->_taxCategoriesById) || !array_key_exists($taxCategoryId, $this->_taxCategoriesById))
        )
        {
            $result = Commerce_TaxCategoryRecord::model()->findById($taxCategoryId);

            if ($result) {
                $taxCategory = Commerce_TaxCategoryModel::populateModel($result);
            }
            else
            {
                $taxCategory = null;
            }

            $this->_taxCategoriesById[$taxCategoryId] = $taxCategory;
        }

        if (isset($this->_taxCategoriesById[$taxCategoryId]))
        {
            return $this->_taxCategoriesById[$taxCategoryId];
        }
    }

    /**
     * Id of default tax category
     *
     * @return int|null
     */
    public function getDefaultId()
    {
        foreach($this->getAll() as $taxCategory){
            if ($taxCategory->default) {
                return $taxCategory->id;
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
    public function save(Commerce_TaxCategoryModel $model)
    {
        if ($model->id) {
            $record = Commerce_TaxCategoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
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
            $this->_taxCategoriesById[$record->id] = $model;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteById($id)
    {
        $all = $this->getAll();
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
    public function getAll($indexBy = null)
    {
        if (!$this->_fetchedAllTaxCategories) {
            $results = Commerce_TaxCategoryRecord::model()->findAll();

            foreach($results as $result){
                $taxCategory = Commerce_TaxCategoryModel::populateModel($result);
                $this->_taxCategoriesById[$taxCategory->id] = $taxCategory;
            }

            $this->_fetchedAllTaxCategories = true;
        }

        if ($indexBy == 'id')
        {
            $taxCategories = $this->_taxCategoriesById;
        }
        else if (!$indexBy)
        {
            $taxCategories = array_values($this->_taxCategoriesById);
        }
        else
        {
            $taxCategories = array();
            foreach ($this->_taxCategoriesById as $taxCategory)
            {
                $taxCategories[$taxCategory->$indexBy] = $taxCategory;
            }
        }

        return $taxCategories;
    }
}
