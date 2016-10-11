<?php
namespace Craft;

/**
 * Shipping category service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ShippingCategoriesService extends BaseApplicationComponent
{

    /**
     * @var bool
     */
    private $_fetchedAllShippingCategories = false;

    /**
     * @var
     */
    private $_shippingCategoriesById;

    /**
     * @var
     */
    private $_shippingCategoriesByHandle;

    /**
     * @param int $shippingCategoryId
     *
     * @return Commerce_ShippingCategoryModel|null
     */
    public function getShippingCategoryById($shippingCategoryId)
    {
        if(!$this->_fetchedAllShippingCategories &&
            (!isset($this->_shippingCategoriesById) || !array_key_exists($shippingCategoryId, $this->_shippingCategoriesById))
        )
        {
            $result = Commerce_ShippingCategoryRecord::model()->findById($shippingCategoryId);

            if ($result) {
                $shippingCategory = $this->_populateShippingCategory($result);
            } else {
                // Remember that this ID doesn't exist
                $this->_shippingCategoriesById[$shippingCategoryId] = null;
            }
        }

        return $this->_shippingCategoriesById[$shippingCategoryId];
    }

    /**
     * @param int $shippingCategoryHandle
     *
     * @return Commerce_ShippingCategoryModel|null
     */
    public function getShippingCategoryByHandle($shippingCategoryHandle)
    {
        if(!$this->_fetchedAllShippingCategories &&
            (!isset($this->_shippingCategoriesByHandle) || !array_key_exists($shippingCategoryHandle, $this->_shippingCategoriesByHandle))
        )
        {
            $result = Commerce_ShippingCategoryRecord::model()->findByAttributes([
                'handle' => $shippingCategoryHandle
            ]);

            if ($result) {
                $shippingCategory = $this->_populateShippingCategory($result);
            } else {
                // Remember that this handle doesn't exist
                $this->_shippingCategoriesByHandle[$shippingCategoryHandle] = null;
            }
        }

        return $this->_shippingCategoriesByHandle[$shippingCategoryHandle];
    }

    /**
     * Id of default shipping category
     *
     * @return int|null
     */
    public function getDefaultShippingCategory()
    {
        foreach($this->getAllShippingCategories() as $shippingCategory){
            if ($shippingCategory->default) {
                return $shippingCategory;
            }
        }

        return null;
    }

    /**
     * @param Commerce_ShippingCategoryModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveShippingCategory(Commerce_ShippingCategoryModel $model)
    {
        if ($model->id) {
            $record = Commerce_ShippingCategoryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No shipping category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }

            $oldHandle = $record->handle;
        } else {
            $record = new Commerce_ShippingCategoryRecord();
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
                Commerce_ShippingCategoryRecord::model()->updateAll(['default' => 0],
                    'id != ?', [$record->id]);
            }

            // Update Service cache
            $this->_memoizeShippingCategory($model);

            if (isset($oldHandle) && $model->handle != $oldHandle) {
                unset($this->_shippingCategoriesByHandle[$oldHandle]);
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
    public function deleteShippingCategoryById($id)
    {
        $all = $this->getAllShippingCategories();
        if (count($all) == 1) {
            return false;
        }

        return (bool)Commerce_ShippingCategoryRecord::model()->deleteByPk($id);
    }

    /**
     * Returns all Shipping Categories
     *
     * @param string|null $indexBy
     * @return Commerce_ShippingCategoryModel[]
     */
    public function getAllShippingCategories($indexBy = null)
    {
        if (!$this->_fetchedAllShippingCategories) {
            $results = Commerce_ShippingCategoryRecord::model()->findAll();

            foreach($results as $result){
                $this->_populateShippingCategory($result);
            }

            $this->_fetchedAllShippingCategories = true;
        }

        if ($indexBy == 'id') {
            $shippingCategories = array_filter($this->_shippingCategoriesById);
        } else if (!$indexBy) {
            $shippingCategories = array_values(array_filter($this->_shippingCategoriesById));
        } else {
            $shippingCategories = array();

            foreach (array_filter($this->_shippingCategoriesById) as $shippingCategory) {
                $shippingCategories[$shippingCategory->$indexBy] = $shippingCategory;
            }
        }

        return $shippingCategories;
    }

    /**
     * Populates, memoizes, and returns a shipping category model based on a given set of values or model/record.
     *
     * @param mixed $values
     *
     * @return Commerce_ShippingCategoryModel
     */
    private function _populateShippingCategory($values)
    {
        $shippingCategory = Commerce_ShippingCategoryModel::populateModel($values);

        if ($shippingCategory->id) {
            $this->_memoizeShippingCategory($shippingCategory);
        }

        return $shippingCategory;
    }

    /**
     * Memoizes a shipping category model by its ID and handle.
     *
     * @param Commerce_ShippingCategoryModel $shippingCategory
     *
     * @return void
     */
    private function _memoizeShippingCategory(Commerce_ShippingCategoryModel $shippingCategory)
    {
        $this->_shippingCategoriesById[$shippingCategory->id] = $shippingCategory;
        $this->_shippingCategoriesByHandle[$shippingCategory->handle] = $shippingCategory;
    }
}
