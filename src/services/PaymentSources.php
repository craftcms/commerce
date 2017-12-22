<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\models\Customer;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Payment Sources service.
 *
 * @property Customer         $savedCustomer
 * @property array|Customer[] $allCustomers
 * @property mixed            $lastUsedAddresses
 * @property int              $customerId
 * @property Customer         $customer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class PaymentSources extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns all payment sources for a user by the user's id.
     *
     * @param int $userId The user id.
     *
     * @return PaymentSource[]
     */
    public function getAllPaymentSourcesByUserId(int $userId): array
    {
        $results = $this->_createPaymentSourcesQuery()
            ->where(['userId' => $userId])
            ->all();

        $sources = [];

        foreach ($results as $result) {
            $sources[] = new PaymentSource($result);
        }

        return $sources;
    }

    /**
     * Returns a payment source by it's id.
     *
     * @param int $sourceId The source id.

     * @return PaymentSource|null
     */
    public function getPaymentSourceById(int $sourceId)
    {
        $result = $this->_createPaymentSourcesQuery()
            ->where(['id' => $sourceId])
            ->one();

        return $result ? new PaymentSource($result) : null;
    }

    /**
     * Create a payment source for a user in the gateway based on a payment form.
     *
     * @param int              $userId            The user id
     * @param GatewayInterface $gateway           The gateway
     * @param BasePaymentForm  $paymentForm       The payment form to use
     * @param string           $sourceDescription The payment form to use
     *
     * @return bool|PaymentSource The saved payment source.
     * @throws Exception if unable to create the payment source
     */
    public function createPaymentSource(int $userId, GatewayInterface $gateway, BasePaymentForm $paymentForm, string $sourceDescription = null)
    {
        $source = $gateway->createPaymentSource($paymentForm);
        $source->userId = $userId;

        if (!empty($sourceDescription)) {
            $source->description = $sourceDescription;
        }

        return $this->savePaymentSource($source) ? $source : false;
    }

    /**
     * Save a payment source.
     *
     * @param PaymentSource $paymentSource The payment source being saved.
     *
     * @return bool Whether the payment source was saved successfully
     * @throws Exception if payment source not found by id.
     */
    public function savePaymentSource(PaymentSource $paymentSource): bool
    {
        if ($paymentSource->id) {
            $record = PaymentSourceRecord::findOne($paymentSource->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No payment source exists with the ID “{id}”',
                    ['id' => $paymentSource->id]));
            }
        } else {
            $record = new PaymentSourceRecord();
        }

        $record->userId = $paymentSource->userId;
        $record->gatewayId = $paymentSource->gatewayId;
        $record->token = $paymentSource->token;
        $record->description = $paymentSource->description;
        $record->response = $paymentSource->response;

        $record->validate();
        $paymentSource->addErrors($record->getErrors());

        if (!$paymentSource->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $paymentSource->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * Delete a payment source by it's id.
     *
     * @param int $id The id
     *
     * @return bool
     * @throws \Throwable in case something went wrong when deleting.
     */
    public function deletePaymentSourceById($id): bool
    {
        $record = PaymentSourceRecord::findOne($id);

        if ($record) {
            $gateway = Commerce::getInstance()->getGateways()->getGatewayById($record->gatewayId);

            if ($gateway) {
                $gateway->deletePaymentSource($record->token);
            }

            return (bool)$record->delete();
        }

        return false;
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving gateways.
     *
     * @return Query The query object.
     */
    private function _createPaymentSourcesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'gatewayId',
                'userId',
                'token',
                'description',
                'response',
            ])
            ->from(['{{%commerce_paymentsources}}']);
    }

}
