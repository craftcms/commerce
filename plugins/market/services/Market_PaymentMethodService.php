<?php
namespace Craft;

/**
 * Class Market_PaymentMethodService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_PaymentMethodService extends BaseApplicationComponent
{
    const CP_ENABLED = 'cpEnabled';
    const FRONTEND_ENABLED = 'frontendEnabled';

    /**
     * @param int $id
     *
     * @return Market_PaymentMethodModel
     */
    public function getById($id)
    {
        $record = Market_PaymentMethodRecord::model()->findById($id);

        return Market_PaymentMethodModel::populateModel($record);
    }

    /**
     * @return Market_PaymentMethodModel[]
     */
    public function getAllForCp()
    {
        $records = Market_PaymentMethodRecord::model()->findAllByAttributes([self::CP_ENABLED => true]);

        return Market_PaymentMethodModel::populateModels($records);
    }

    /**
     * @return Market_PaymentMethodModel[]
     */
    public function getAllForFrontend()
    {
        $records = Market_PaymentMethodRecord::model()->findAllByAttributes([self::FRONTEND_ENABLED => true]);

        return Market_PaymentMethodModel::populateModels($records);
    }

    /**
     * @return Market_PaymentMethodModel[]
     */
    public function getAllPossibleGateways()
    {
        $paymentMethods = [];
        $gateways       = craft()->market_gateway->getGateways();

        foreach ($gateways as $gateway) {
            $paymentMethods[] = $this->getByClass($gateway->getShortName());
        }

        return $paymentMethods;
    }

    /**
     * @param string $class
     *
     * @return Market_PaymentMethodModel
     */
    public function getByClass($class)
    {
        $record = Market_PaymentMethodRecord::model()->findByAttributes(['class' => $class]);

        if ($record) {
            $model = Market_PaymentMethodModel::populateModel($record);
        } else {
            $gateway = craft()->market_gateway->getGateway($class);

            $model           = new Market_PaymentMethodModel;
            $model->class    = $gateway->getShortName();
            $model->name     = $gateway->getName();
            $model->settings = $gateway->getDefaultParameters();
        }

        return $model;
    }

    /**
     * @param Market_PaymentMethodModel $model
     *
     * @return bool
     * @throws Exception
     */
    public function save(Market_PaymentMethodModel $model)
    {
        $record = Market_PaymentMethodRecord::model()->findByAttributes(['class' => $model->class]);
        if (!$record) {
            $gateway = craft()->market_gateway->getGateway($model->class);

            if (!$gateway) {
                throw new Exception(Craft::t('No gateway exists with the class name “{class}”',
                    ['class' => $model->class]));
            }
            $record       = new Market_PaymentMethodRecord();
            $record->name = $gateway->getName();
        }

        $record->class           = $model->class;
        $record->settings        = $model->settings;
        $record->cpEnabled       = $model->cpEnabled;
        $record->frontendEnabled = $model->frontendEnabled;

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
}