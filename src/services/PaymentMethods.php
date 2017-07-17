<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\PaymentMethod;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentMethod as PaymentMethodRecord;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use yii\base\Component;
use yii\base\Exception;

/**
 * Payment method service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 *
 */
class PaymentMethods extends Component
{
    const FRONTEND_ENABLED = 'frontendEnabled';

    /**
     * Get all frontend enabled payment methods.
     *
     * @return PaymentMethod[] All payment methods that are enabled for frontend
     */
    public function getAllFrontEndPaymentMethods(): array
    {
        $rows = $this->_createPaymentMethodsQuery()
            ->where(Db::parseParam('isArchived', ':empty:'))
            ->andWhere(Db::parseParam('frontEndEnabled', 'not :empty:'))
            ->orderBy('sortOrder')
            ->all();

        $methods = [];
        
        foreach ($rows as $row) {
            $row['settings'] = Json::decodeIfJson($row['settings']);
            $paymentMethod = new PaymentMethod($row);
            $this->_overridepaymentMethodSettings($paymentMethod);
            $methods[] = $paymentMethod;
        }

        return $methods;
    }

    /**
     * Get all  payment methods.
     *
     * @return PaymentMethod[] All payment methods
     */
    public function getAllPaymentMethods(): array
    {
        $rows = $this->_createPaymentMethodsQuery()
            ->where(Db::parseParam('isArchived', ':empty:'))
            ->orderBy('sortOrder')
            ->all();

        $methods = [];

        foreach ($rows as $row) {
            $row['settings'] = Json::decodeIfJson($row['settings']);
            $paymentMethod = new PaymentMethod($row);
            $this->_overridepaymentMethodSettings($paymentMethod);
            $methods[] = $paymentMethod;
        }

        return $methods;
    }

    /**
     * Archive a payment method by it's id.
     *
     * @param int $id payment method id.
     *
     * @return bool Whether the archiving was successful or not
     */
    public function archivePaymentMethodById(int $id): bool
    {
        $paymentMethod = $this->getPaymentMethodById($id);
        $paymentMethod->isArchived = true;
        $paymentMethod->dateArchived = Db::prepareDateForDb(new \DateTime());

        return $this->savePaymentMethod($paymentMethod);
    }

    /**
     * Get a payment method by it's id.
     *
     * @param int $id
     *
     * @return PaymentMethod|null The payment method or null if not found.
     */
    public function getPaymentMethodById(int $id)
    {
        $row = $this->_createPaymentMethodsQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        $paymentMethod = new PaymentMethod($row);
        $this->_overridepaymentMethodSettings($paymentMethod);

        return $paymentMethod;
    }

    /**
     * Save a payment method.
     *
     * @param PaymentMethod $model The payment method to be saved.
     *
     * @return bool Whether the payment method was saved successfully or not.
     * @throws Exception
     */
    public function savePaymentMethod(PaymentMethod $model): bool
    {
        if ($model->id) {
            $record = PaymentMethodRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(\Craft::t('commerce', 'No payment method exists with the ID “{id}”', ['id' => $model->id]));
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
     * Reorder payment methods by ids.
     *
     * @param array $ids Array of payment methods.
     *
     * @return bool Always true.
     */
    public function reorderPaymentMethods(array $ids): bool
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
            Craft::$app->getDb()->createCommand()->update('commerce_paymentmethods', ['sortOrder' => $sortOrder + 1], ['id' => $id])->execute();
        }

        return true;
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving payment methods,
     *
     * @return Query The query object.
     */
    private function _createPaymentMethodsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'class',
                'name',
                'paymentType',
                'frontendEnabled',
                'isArchived',
                'dateArchived',
                'settings',
            ])
            ->from(['{{%commerce_paymentmethods}}']);
    }

    /**
     * Override payment method settings form config file.
     *
     * @param PaymentMethod $paymentMethod The payment method.
     */
    private function _overridepaymentMethodSettings(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->id) {
            $paymentMethodSettings = Plugin::getInstance()->getSettings()->paymentMethodSettings;

            if (isset($paymentMethodSettings[$paymentMethod->id])) {
                $paymentMethod->settings = array_merge($paymentMethod->settings, $paymentMethodSettings[$paymentMethod->id]);
            }
        }
    }
}
