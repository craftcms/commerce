<?php
namespace Craft;

/**
 * Payment method service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_PaymentMethodsService extends BaseApplicationComponent
{
    const FRONTEND_ENABLED = 'frontendEnabled';

    /**
     * @param int $id
     *
     * @return Commerce_PaymentMethodModel|null
     */
    public function getPaymentMethodById($id)
    {
        $result = Commerce_PaymentMethodRecord::model()->findById($id);

        if ($result)
        {
            return Commerce_PaymentMethodModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_PaymentMethodModel[]
     */
    public function getAllPaymentMethods($criteria = [])
    {
        if(!$criteria)
        {
            $criteria = new \CDbCriteria();
            $criteria->addCondition("isArchived=:xIsArchived");
            $criteria->order = 'sortOrder';
            $criteria->params = [':xIsArchived' => false];
        }

        $records = Commerce_PaymentMethodRecord::model()->findAll($criteria);
        return Commerce_PaymentMethodModel::populateModels($records);
    }

    /**
     * @return Commerce_PaymentMethodModel[]
     */
    public function getAllFrontEndPaymentMethods()
    {
        $criteria = new \CDbCriteria();
        $criteria->addCondition("frontEndEnabled=:xFrontEndEnabled");
        $criteria->addCondition("isArchived=:xIsArchived");
        $criteria->params = [':xFrontEndEnabled' => true, ':xIsArchived' => false];
        $criteria->order = 'sortOrder';

        return $this->getAllPaymentMethods($criteria);
    }

    /**
     * @param Commerce_PaymentMethodModel $model
     *
     * @return bool
     * @throws Exception
     */
    public function savePaymentMethod(Commerce_PaymentMethodModel $model)
    {
        if ($model->id)
        {
            $record = Commerce_PaymentMethodRecord::model()->findById($model->id);

            if (!$record)
            {
                throw new Exception(Craft::t('No payment method exists with the ID “{id}”', ['id' => $model->id]));
            }
        }
        else
        {
            $record = new Commerce_PaymentMethodRecord();
        }

        $gatewayAdapter = $model->getGatewayAdapter(); //getGatewayAdapter sets gatewayAdapter settings automatically

        $record->settings = $gatewayAdapter ? $gatewayAdapter->getAttributes() : [];
        $record->name = $model->name;
        $record->paymentType = $model->paymentType;
        $record->class = $model->class;
        $record->frontendEnabled = $model->frontendEnabled;
        $record->isArchived = $model->isArchived;
        $record->dateArchived = $model->dateArchived;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$gatewayAdapter)
        {
            $model->clearErrors('class');
        }

        if ($gatewayAdapter && !$gatewayAdapter->validate())
        {
            $model->addError('settings', $gatewayAdapter->getErrors());
        }

        if (!$model->hasErrors())
        {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @param $id
     *
     * @return bool
     * @throws Exception
     */
    public function archivePaymentMethod($id)
    {
        $paymentMethod = $this->getPaymentMethodById($id);

        $paymentMethod->isArchived = true;
        $paymentMethod->dateArchived = DateTimeHelper::currentTimeForDb();

        return $this->savePaymentMethod($paymentMethod);
    }

    /**
     * @param $ids
     *
     * @return bool
     */
    public function reorderPaymentMethods($ids)
    {
        $paymentMethods = $this->getAllPaymentMethods();

        $count = 999;
        // Append those not in the table an put them at 999+
        foreach ($paymentMethods as $paymentMethod)
        {
            if ($paymentMethod->isArchived)
            {
                $ids[$count++] = $paymentMethod->id;
            }
        }

        foreach ($ids as $sortOrder => $id)
        {
            craft()->db->createCommand()->update('commerce_paymentmethods',
                ['sortOrder' => $sortOrder + 1], ['id' => $id]);
        }

        return true;
    }
}
