<?php

namespace craft\commerce\services;

use craft\commerce\models\ShippingCategory;
use craft\commerce\records\ShippingCategory as ShippingCategoryRecord;
use yii\base\Component;

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
class ShippingCategories extends Component
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
     * @return ShippingCategory|null
     */
    public function getShippingCategoryById($shippingCategoryId)
    {
        if (!$this->_fetchedAllShippingCategories &&
            (null === $this->_shippingCategoriesById || !array_key_exists($shippingCategoryId, $this->_shippingCategoriesById))
        ) {
            $result = ShippingCategoryRecord::findOne($shippingCategoryId);

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
     * Populates, memoize, and return a shipping category model based on a given set of values or model/record.
     *
     * @param mixed $values
     *
     * @return ShippingCategory
     */
    private function _populateShippingCategory($values)
    {
        $shippingCategory = new ShippingCategory($values);

        if ($shippingCategory->id) {
            $this->_memoizeShippingCategory($shippingCategory);
        }

        return $shippingCategory;
    }

    /**
     * Memoize a shipping category model by its ID and handle.
     *
     * @param ShippingCategory $shippingCategory
     *
     * @return void
     */
    private function _memoizeShippingCategory(ShippingCategory $shippingCategory)
    {
        $this->_shippingCategoriesById[$shippingCategory->id] = $shippingCategory;
        $this->_shippingCategoriesByHandle[$shippingCategory->handle] = $shippingCategory;
    }

    /**
     * @param int $shippingCategoryHandle
     *
     * @return ShippingCategory|null
     */
    public function getShippingCategoryByHandle($shippingCategoryHandle)
    {
        if (!$this->_fetchedAllShippingCategories &&
            (null === $this->_shippingCategoriesByHandle || !array_key_exists($shippingCategoryHandle, $this->_shippingCategoriesByHandle))
        ) {
            $result = ShippingCategoryRecord::find()->where([
                'handle' => $shippingCategoryHandle
            ])->all();

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
     * @return ShippingCategory|null
     */
    public function getDefaultShippingCategory()
    {
        foreach ($this->getAllShippingCategories() as $shippingCategory) {
            if ($shippingCategory->default) {
                return $shippingCategory;
            }
        }

        return null;
    }

    /**
     * Returns all Shipping Categories
     *
     * @param string|null $indexBy
     *
     * @return ShippingCategory[]
     */
    public function getAllShippingCategories($indexBy = null)
    {
        if (!$this->_fetchedAllShippingCategories) {
            $results = ShippingCategoryRecord::findAll();

            foreach ($results as $result) {
                $this->_populateShippingCategory($result);
            }

            $this->_fetchedAllShippingCategories = true;
        }

        if ($indexBy == 'id') {
            $shippingCategories = array_filter($this->_shippingCategoriesById);
        } else if (!$indexBy) {
            $shippingCategories = array_values(array_filter($this->_shippingCategoriesById));
        } else {
            $shippingCategories = [];

            foreach (array_filter($this->_shippingCategoriesById) as $shippingCategory) {
                $shippingCategories[$shippingCategory->$indexBy] = $shippingCategory;
            }
        }

        return $shippingCategories;
    }

    /**
     * @param ShippingCategory $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveShippingCategory(ShippingCategory $model)
    {
        if ($model->id) {
            $record = ShippingCategoryRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No shipping category exists with the ID “{id}”',
                    ['id' => $model->id]));
            }

            $oldHandle = $record->handle;
        } else {
            $record = new ShippingCategoryRecord();
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
                ShippingCategoryRecord::updateAll(['default' => 0], ['id' => $record->id]);
            }

            // Update Service cache
            $this->_memoizeShippingCategory($model);

            if (null !== $oldHandle && $model->handle != $oldHandle) {
                unset($this->_shippingCategoriesByHandle[$oldHandle]);
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
    public function deleteShippingCategoryById($id)
    {
        $all = $this->getAllShippingCategories();
        if (count($all) == 1) {
            return false;
        }

        $record = ShippingCategoryRecord::findOne($id);

        if ($record) {
            $record->delete();
        }
    }
}
