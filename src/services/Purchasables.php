<?php
namespace craft\commerce\services;

use Commerce\Interfaces\Purchasable;
use craft\commerce\helpers\Db;
use craft\commerce\records\Purchasable as PurchasableRecord;
use yii\base\Component;
use Craft;

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
class Purchasables extends Component
{

    /**
     * Returns the purchasable element by the SKU
     *
     * @return mixed|null
     */
    public function getPurchasableBySku($sku)
    {

        $result = Craft::$app->getDb()->createCommand()
            ->select('id')
            ->where("sku=:skux", [':skux' => $sku])
            ->from('commerce_purchasables')
            ->queryScalar();

        if ($result) {
            return Craft::$app->getElements()->getElementById($result);
        }

        return null;
    }

    /**
     * Saves the element and the purchasable. Use this function where you would usually
     * use `Craft::$app->getElements()->saveElement()`
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

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if ($success = Craft::$app->getElements()->saveElement($model)) {
                $id = $model->getPurchasableId();
                $price = $model->getPrice();
                $sku = $model->getSku();

                $purchasable = PurchasableRecord::findOne($id);

                if (!$purchasable) {
                    $purchasable = new PurchasableRecord();
                }

                $purchasable->id = $id;
                $purchasable->price = $price;
                $purchasable->sku = $sku;

                $success = $purchasable->save();

                if (!$success) {
                    $model->addErrors($purchasable->getErrors());
                    $transaction->rollBack();

                    return $success;
                }

                $transaction->commit();
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $success;
    }
}
