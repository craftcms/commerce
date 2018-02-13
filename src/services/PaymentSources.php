<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\events\PaymentSourceEvent;
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
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class PaymentSources extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event PaymentSourceEvent The event that is triggered when a payment source is deleted
     */
    const EVENT_DELETE_PAYMENT_SOURCE = 'deletePaymentSource';

    /**
     * @event PaymentSourceEvent The event that is triggered before a plan is saved.
     */
    const EVENT_BEFORE_SAVE_PAYMENT_SOURCE = 'beforeSavePaymentSource';

    /**
     * @event PaymentSourceEvent The event that is triggered after a plan is saved.
     */
    const EVENT_AFTER_SAVE_PAYMENT_SOURCE = 'afterSavePaymentSource';

    // Public Methods
    // =========================================================================

    /**
     * Returns a user's payment sources, per the user's ID.
     *
     * @param int $userId the user's ID
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
     * Returns a payment source by its ID.
     *
     * @param int $sourceId the source ID
     *
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
     * Creates a payment source for a user in the gateway based on a payment form.
     *
     * @param int              $userId            the user's ID
     * @param GatewayInterface $gateway           the gateway
     * @param BasePaymentForm  $paymentForm       the payment form to use
     * @param string           $sourceDescription the payment form to use
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
     * Saves a payment source.
     *
     * @param PaymentSource $paymentSource The payment source being saved.
     *
     * @return bool Whether the payment source was saved successfully
     * @throws Exception if the payment source couldn't be found
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

        // fire a 'beforeSavePlan' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_PAYMENT_SOURCE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, new PaymentSourceEvent([
                'paymentSource' => $paymentSource,
            ]));
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

            // fire a 'beforeSavePlan' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_PAYMENT_SOURCE)) {
                $this->trigger(self::EVENT_AFTER_SAVE_PAYMENT_SOURCE, new PaymentSourceEvent([
                    'paymentSource' => $paymentSource,
                ]));
            }

            return true;
        }

        return false;
    }

    /**
     * Delete a payment source by its ID.
     *
     * @param int $id The ID
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

            $paymentSource = $this->getPaymentSourceById($id);

            // Fire an 'archivePlan' event.
            if ($this->hasEventHandlers(self::EVENT_DELETE_PAYMENT_SOURCE)) {
                $this->trigger(self::EVENT_DELETE_PAYMENT_SOURCE, new PaymentSourceEvent([
                    'paymentSource' => $paymentSource,
                ]));
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
