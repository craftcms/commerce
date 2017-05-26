<?php

namespace craft\commerce\services;

use craft\commerce\models\PaymentMethod;
use craft\commerce\records\PaymentMethod as PaymentMethodRecord;
use yii\base\Component;

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
class PaymentMethods extends Component
{
    const FRONTEND_ENABLED = 'frontendEnabled';

    /**
     * @return PaymentMethod[]
     */
    public function getAllFrontEndPaymentMethods(): array
    {
        $records = PaymentMethodRecord::find()->where('isArchived=:xIsArchived,frontEndEnabled=:xFrontEndEnabled', [':xFrontEndEnabled' => true, ':xIsArchived' => false])->orderBy('sortOrder')->all();

        return PaymentMethod::populateModels($records);
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return PaymentMethod[]
     */
    public function getAllPaymentMethods($criteria = [])
    {
        $records = PaymentMethodRecord::find()->where('isArchived=:xIsArchived', [':xIsArchived' => false])->orderBy('sortOrder')->all();

        return PaymentMethod::populateModels($records);
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
     * @param int $id
     *
     * @return PaymentMethod|null
     */
    public function getPaymentMethodById($id)
    {
        $result = PaymentMethodRecord::findOne($id);

        if ($result) {
            return PaymentMethod::populateModel($result);
        }

        return null;
    }

    /**
     * @param PaymentMethod $model
     *
     * @return bool
     * @throws Exception
     */
    public function savePaymentMethod(PaymentMethod $model)
    {
        if ($model->id) {
            $record = PaymentMethodRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No payment method exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new PaymentMethodRecord();
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

        if (!$gatewayAdapter) {
            $model->clearErrors('class');
        }

        if ($gatewayAdapter && !$gatewayAdapter->validate()) {
            $model->addError('settings', $gatewayAdapter->getErrors());
        }

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        }

        return false;
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
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->isArchived) {
                $ids[$count++] = $paymentMethod->id;
            }
        }

        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()->update('commerce_paymentmethods',
                ['sortOrder' => $sortOrder + 1], ['id' => $id]);
        }

        return true;
    }
}
