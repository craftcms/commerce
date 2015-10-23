<?php
namespace Craft;

/**
 * Payment method service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_PaymentMethodsService extends BaseApplicationComponent
{
    const FRONTEND_ENABLED = 'frontendEnabled';

    /**
     * @param int $id
     *
     * @return Commerce_PaymentMethodModel
     */
    public function getById($id)
    {
        $record = Commerce_PaymentMethodRecord::model()->findById($id);
        return Commerce_PaymentMethodModel::populateModel($record);
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_PaymentMethodModel[]
     */
    public function getAll($criteria = [])
    {
        $records = Commerce_PaymentMethodRecord::model()->findAll($criteria);
        return Commerce_PaymentMethodModel::populateModels($records);
    }

    /**
     * @return Commerce_PaymentMethodModel[]
     */
    public function getAllForFrontend()
    {
        $records = Commerce_PaymentMethodRecord::model()->findAllByAttributes([self::FRONTEND_ENABLED => true]);

        return Commerce_PaymentMethodModel::populateModels($records);
    }

    /**
     * @param Commerce_PaymentMethodModel $model
     *
     * @return bool
     * @throws Exception
     */
    public function save(Commerce_PaymentMethodModel $model)
    {
        if ($model->id) {
            $record = Commerce_PaymentMethodRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No payment method exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_PaymentMethodRecord();
        }

        $gateway = $model->getGatewayAdapter(); //getGatewayAdapter sets gateway settings automatically

        $record->settings = $gateway->getAttributes();
        $record->name = $model->name;
        $record->class = $model->class;
        $record->frontendEnabled = $model->frontendEnabled;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$gateway->validate()) {
            $model->addError('settings', $gateway->getErrors());
        }
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
