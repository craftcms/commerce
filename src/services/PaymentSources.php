<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\events\PaymentSourceEvent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\db\Query;
use Throwable;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Payment Sources service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentSources extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event PaymentSourceEvent The event that is triggered when a payment source is deleted
     *
     * Plugins can get notified when a payment source is deleted.
     *
     * ```php
     * use craft\commerce\events\PaymentSourceEvent;
     * use craft\commerce\services\PaymentSources;
     * use yii\base\Event;
     *
     * Event::on(PaymentSources::class, PaymentSources::EVENT_DELETE_PAYMENT_SOURCE, function(PaymentSourceEvent $e) {
     *     // Do something - perhaps warn a user they have no valid payment sources saved.
     * });
     * ```
     */
    const EVENT_DELETE_PAYMENT_SOURCE = 'deletePaymentSource';

    /**
     * @event PaymentSourceEvent The event that is triggered before a plan is saved.
     *
     * Plugins can get notified before a payment source is added.
     *
     * ```php
     * use craft\commerce\events\PaymentSourceEvent;
     * use craft\commerce\services\PaymentSources;
     * use yii\base\Event;
     *
     * Event::on(PaymentSources::class, PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, function(PaymentSourceEvent $e) {
     *     // Do something
     * });
     * ```
     */
    const EVENT_BEFORE_SAVE_PAYMENT_SOURCE = 'beforeSavePaymentSource';

    /**
     * @event PaymentSourceEvent The event that is triggered after a plan is saved.
     *
     * Plugins can get notified after a payment source is added.
     *
     * ```php
     * use craft\commerce\events\PaymentSourceEvent;
     * use craft\commerce\services\PaymentSources;
     * use yii\base\Event;
     *
     * Event::on(PaymentSources::class, PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, function(PaymentSourceEvent $e) {
     *     // Do something - perhaps settle any outstanding balance
     * });
     * ```
     */
    const EVENT_AFTER_SAVE_PAYMENT_SOURCE = 'afterSavePaymentSource';

    // Public Methods
    // =========================================================================

    /**
     * Returns a user's payment sources, per the user's ID.
     *
     * @param int|null $userId the user's ID
     * @return PaymentSource[]
     */
    public function getAllPaymentSourcesByUserId(int $userId = null): array
    {
        if (null === $userId) {
            return [];
        }

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
     * Returns a user's payment sources on a gateway, per the user's ID.
     *
     * @param int|null $gatewayId the gateway's ID
     * @param int|null $userId the user's ID
     * @return PaymentSource[]
     */
    public function getAllGatewayPaymentSourcesByUserId(int $gatewayId = null, int $userId = null): array
    {
        if (null === $gatewayId || null === $userId) {
            return [];
        }

        $results = $this->_createPaymentSourcesQuery()
            ->where(['userId' => $userId])
            ->andWhere(['gatewayId' => $gatewayId])
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
     * Returns a payment source by its ID and user ID.
     *
     * @param int $sourceId the source ID
     * @param int $userId the source's user ID
     * @return PaymentSource|null
     */
    public function getPaymentSourceByIdAndUserId(int $sourceId, int $userId)
    {
        $result = $this->_createPaymentSourcesQuery()
            ->where(['id' => $sourceId])
            ->andWhere(['userId' => $userId])
            ->one();

        return $result ? new PaymentSource($result) : null;
    }

    /**
     * Creates a payment source for a user in the gateway based on a payment form.
     *
     * @param int $userId the user's ID
     * @param GatewayInterface $gateway the gateway
     * @param BasePaymentForm $paymentForm the payment form to use
     * @param string $sourceDescription the payment form to use
     * @return PaymentSource The saved payment source.
     * @throws PaymentSourceException If unable to create the payment source
     */
    public function createPaymentSource(int $userId, GatewayInterface $gateway, BasePaymentForm $paymentForm, string $sourceDescription = null): PaymentSource
    {
        $source = $gateway->createPaymentSource($paymentForm, $userId);
        $source->userId = $userId;

        if (!empty($sourceDescription)) {
            $source->description = $sourceDescription;
        }

        if (!$this->savePaymentSource($source)) {
            throw new PaymentSourceException(Craft::t('commerce', 'Could not create the payment source.'));
        }

        return $source;
    }

    /**
     * Saves a payment source.
     *
     * @param PaymentSource $paymentSource The payment source being saved.
     * @param bool $runValidation should we validate this payment source before saving.
     * @return bool Whether the payment source was saved successfully
     * @throws InvalidConfigException if the payment source couldn't be found
     */
    public function savePaymentSource(PaymentSource $paymentSource, bool $runValidation = true): bool
    {
        if ($paymentSource->id) {
            $record = PaymentSourceRecord::findOne($paymentSource->id);

            if (!$record) {
                throw new InvalidConfigException(Craft::t('commerce', 'No payment source exists with the ID “{id}”',
                    ['id' => $paymentSource->id]));
            }
        } else {
            $record = new PaymentSourceRecord();
        }

        // fire a 'beforeSavePaymentSource' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_PAYMENT_SOURCE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, new PaymentSourceEvent([
                'paymentSource' => $paymentSource,
            ]));
        }

        if ($runValidation && !$paymentSource->validate()) {
            Craft::info('Payment source not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->userId = $paymentSource->userId;
        $record->gatewayId = $paymentSource->gatewayId;
        $record->token = $paymentSource->token;
        $record->description = $paymentSource->description;
        $record->response = $paymentSource->response;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $paymentSource->id = $record->id;

        // fire a 'afterSavePaymentSource' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_PAYMENT_SOURCE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_PAYMENT_SOURCE, new PaymentSourceEvent([
                'paymentSource' => $paymentSource,
            ]));
        }

        return true;
    }

    /**
     * Delete a payment source by its ID.
     *
     * @param int $id The ID
     * @return bool
     * @throws Throwable in case something went wrong when deleting.
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

            // Fire an 'deletePaymentSource' event.
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
