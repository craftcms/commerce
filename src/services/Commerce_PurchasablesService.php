<?php
namespace Craft;

use Commerce\Interfaces\Purchasable;

/**
 * Purchasable service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_PurchasablesService extends BaseApplicationComponent
{

    /**
     * Returns the purchasable element by the SKU
     *
     * @return mixed|null
     */
    public function getPurchasableBySku($sku){

        $result = craft()->db->createCommand()
            ->select('id')
            ->where("sku=:skux", [':skux' => $sku])
            ->from('commerce_purchasables')
            ->queryScalar();

        if($result){
            return craft()->elements->getElementById($result);
        }

        return null;
    }

    /**
     * Saves the element and the purchasable. Use this function where you would usually
     * use `craft()->elements->saveElement()`
     *
     * @param BaseElementModel $model
     *
     * @return bool
     * @throws \Exception
     */
    public function saveElement(BaseElementModel $model)
    {
        if (!$model instanceof Purchasable) {
            throw new Exception('Trying to save a purchasable element that is not a purchasable.');
        }

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        try {
            if ($success = craft()->elements->saveElement($model)) {
                $id = $model->getPurchasableId();
                $price = $model->getPrice();
                $sku = $model->getSku();

                $purchasable = Commerce_PurchasableRecord::model()->findById($id);

                if (!$purchasable) {
                    $purchasable = new Commerce_PurchasableRecord();
                }

                $purchasable->id = $id;
                $purchasable->price = $price;
                $purchasable->sku = $sku;

                $success = $purchasable->save();

                if (!$success) {
                    $model->addErrors($purchasable->getErrors());
                    if ($transaction !== null)
                    {
                        $transaction->rollback();
                    }

                    return $success;
                }

                if ($transaction !== null)
                {
                    $transaction->commit();
                }
            }
        } catch (\Exception $e) {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        return $success;
    }
}
