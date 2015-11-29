<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;
use Commerce\Interfaces\ShippingMethod;

/**
 * Shipping method service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ShippingMethodsService extends BaseApplicationComponent
{
    /**
     * @var bool
     */
    private $_shippingMethods;

    /**
     * @param int $id
     *
     * @return Commerce_ShippingMethodModel|null
     */
    public function getShippingMethodById($id)
    {
        $result = Commerce_ShippingMethodRecord::model()->findById($id);

        if ($result) {
            return Commerce_ShippingMethodModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param string $handle
     *
     * @return \Commerce\Interfaces\ShippingMethod|null
     */
    public function getShippingMethodByHandle($handle)
    {
        $methods = $this->getAllShippingMethods();

        foreach ($methods as $method) {
            if ($method->getHandle() == $handle) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Returns the Commerce managed and 3rd party shipping methods
     *
     * @return Commerce_ShippingMethodModel[]
     */
    public function getAllShippingMethods()
    {
        if (!isset($this->_shippingMethods)) {
            $methods = $this->getAllCoreShippingMethods();

            $additionalMethods = craft()->plugins->call('commerce_registerShippingMethods');

            foreach ($additionalMethods as $additional) {
                $methods = array_merge($methods, $additional);
            }

            $this->_shippingMethods = $methods;
        }

        return $this->_shippingMethods;
    }

    /**
     * Returns the Commerce managed shipping methods
     *
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_ShippingMethodModel[]
     */
    public function getAllCoreShippingMethods($criteria = [])
    {
        $records = Commerce_ShippingMethodRecord::model()->findAll($criteria);

        $methods = Commerce_ShippingMethodModel::populateModels($records);

        return $methods;

    }

    /**
     * Returns the Commerce managed shipping methods
     *
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_ShippingMethodModel[]
     */
    public function getAllThirdPartyShippingMethods($criteria = [])
    {
        $methods = [];

        $additionalMethods = craft()->plugins->call('commerce_registerShippingMethods');

        foreach ($additionalMethods as $additional) {
            $methods = array_merge($methods, $additional);
        }

        return $methods;

    }

    /**
     * @return bool
     */
    public function ShippingMethodExists()
    {
        return Commerce_ShippingMethodRecord::model()->exists();
    }

    /**
     * @param Commerce_OrderModel $cart
     *
     * @return array
     */
    public function getAvailableShippingMethods(Commerce_OrderModel $cart)
    {
        $availableMethods = [];
        $methods = $this->getAllShippingMethods();

        foreach ($methods as $method) {
            if ($method->getIsEnabled()) {
                if ($rule = $this->getMatchingShippingRule($cart, $method)) {
                    $amount = $rule->getBaseRate();
                    $amount += $rule->getPerItemRate() * $cart->totalQty;
                    $amount += $rule->getWeightRate() * $cart->totalWeight;
                    $amount += $rule->getPercentageRate() * $cart->itemTotal;
                    $amount = max($amount, $rule->getMinRate() * 1);

                    if ($rule->getMaxRate() * 1) {
                        $amount = min($amount, $rule->getMaxRate() * 1);
                    }

                    $availableMethods[$method->getHandle()] = [
                        'name' => $method->getName(),
                        'description' => $rule->getDescription(),
                        'amount' => $amount,
                        'handle' => $method->getHandle(),
                        'type' => $method->getType()
                    ];
                }
            }
        }

        return $availableMethods;
    }

    /**
     * @param Commerce_OrderModel $cart
     * @return array
     */
    public function getOrderedAvailableShippingMethods($cart)
    {
        $availableMethods = $this->getAvailableShippingMethods($cart);

        uasort($availableMethods, function($a, $b) {
            return $a['amount'] - $b['amount'];
        });

        return $availableMethods;

    }

    /**
     * @param Commerce_OrderModel $order
     * @param \Commerce\Interfaces\ShippingMethod $method
     *
     * @return bool|Commerce_ShippingRuleModel
     */
    public function getMatchingShippingRule(
        Commerce_OrderModel $order,
        ShippingMethod $method
    )
    {
        foreach ($method->getRules() as $rule) {
            /** @var \Commerce\Interfaces\ShippingRule $rule */
            if ($rule->matchOrder($order)) {
                return $rule;
            }
        }

        return false;
    }

    /**
     * @param Commerce_ShippingMethodModel $model
     *
     * @return bool
     * @throws \Exception
     */
    public function saveShippingMethod(Commerce_ShippingMethodModel $model)
    {
        if ($model->id) {
            $record = Commerce_ShippingMethodRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No shipping method exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_ShippingMethodRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->enabled = $model->enabled;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }


    /**
     * @param $model
     *
     * @return bool
     */
    public function delete($model)
    {
        // Delete all rules first.
        CommerceDbHelper::beginStackedTransaction();
        try {

            $rules = craft()->commerce_shippingRules->getAllShippingRulesByShippingMethodId($model->id);
            foreach ($rules as $rule) {
                craft()->commerce_shippingRules->deleteShippingRuleById($rule->id);
            }

            Commerce_ShippingMethodRecord::model()->deleteByPk($model->id);

            CommerceDbHelper::commitStackedTransaction();

            return true;
        } catch (\Exception $e) {
            CommerceDbHelper::rollbackStackedTransaction();

            return false;
        }

        CommerceDbHelper::rollbackStackedTransaction();

        return false;
    }
}
