<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\db\Table;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\events\PaymentSourceEvent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin;
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
    /**
     * @event PaymentSourceEvent The event that is triggered when a payment source is deleted.
     *
     * ```php
     * use craft\commerce\events\PaymentSourceEvent;
     * use craft\commerce\services\PaymentSources;
     * use craft\commerce\models\PaymentSource;
     * use yii\base\Event;
     *
     * Event::on(
     *     PaymentSources::class,
     *     PaymentSources::EVENT_DELETE_PAYMENT_SOURCE,
     *     function(PaymentSourceEvent $event) {
     *         // @var PaymentSource $source
     *         $source = $event->paymentSource;
     *
     *         // Warn a user they don’t have any valid payment sources saved
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_DELETE_PAYMENT_SOURCE = 'deletePaymentSource';

    /**
     * @event PaymentSourceEvent The event that is triggered before a payment source is added.
     *
     * ```php
     * use craft\commerce\events\PaymentSourceEvent;
     * use craft\commerce\services\PaymentSources;
     * use craft\commerce\models\PaymentSource;
     * use yii\base\Event;
     *
     * Event::on(
     *     PaymentSources::class,
     *     PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE,
     *     function(PaymentSourceEvent $event) {
     *         // @var PaymentSource $source
     *         $source = $event->paymentSource;
     *
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SAVE_PAYMENT_SOURCE = 'beforeSavePaymentSource';

    /**
     * @event PaymentSourceEvent The event that is triggered after a payment source is added.
     *
     * ```php
     * use craft\commerce\events\PaymentSourceEvent;
     * use craft\commerce\services\PaymentSources;
     * use craft\commerce\models\PaymentSource;
     * use yii\base\Event;
     *
     * Event::on(
     *     PaymentSources::class,
     *     PaymentSources::EVENT_AFTER_SAVE_PAYMENT_SOURCE,
     *     function(PaymentSourceEvent $event) {
     *         // @var PaymentSource $source
     *         $source = $event->paymentSource;
     *
     *         // Settle any outstanding balance
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_SAVE_PAYMENT_SOURCE = 'afterSavePaymentSource';


    /**
     * Returns a customer's payment sources, per the customer's ID.
     *
     * @param int|null $customerId the user's ID
     * @return PaymentSource[]
     * @noinspection PhpUnused
     */
    public function getAllPaymentSourcesByCustomerId(int $customerId = null): array
    {
        if ($customerId === null) {
            return [];
        }

        $results = $this->_createPaymentSourcesQuery()
            ->where(['customerId' => $customerId])
            ->all();

        $sources = [];

        foreach ($results as $result) {
            $sources[] = new PaymentSource($result);
        }

        return $sources;
    }

    /**
     * @deprecated in 4.0.0. Use [[getAllPaymentSourcesByCustomerId()]] instead.
     */
    public function getAllPaymentSourcesByUserId(): array
    {
        Craft::$app->getDeprecator()->log('PaymentSources::getAllPaymentSourcesByUserId()', 'The `PaymentSources::getAllPaymentSourcesByUserId()` is deprecated, use the `PaymentSources::getAllPaymentSourcesByCustomerId()` instead.');
        return $this->getAllPaymentSourcesByCustomerId();
    }

    /**
     * Returns all payment sources for a gateway.
     *
     * @param int|null $gatewayId the gateway's ID
     * @return PaymentSource[]
     */
    public function getAllPaymentSourcesByGatewayId(int $gatewayId = null): array
    {
        if ($gatewayId === null) {
            return [];
        }

        $results = $this->_createPaymentSourcesQuery()
            ->where(['gatewayId' => $gatewayId])
            ->all();

        $sources = [];

        foreach ($results as $result) {
            $sources[] = new PaymentSource($result);
        }

        return $sources;
    }

    /**
     * Returns a customer's payment sources on a gateway, per the customer/user's ID.
     *
     * @param int|null $gatewayId the gateway's ID
     * @param int|null $customerId the user's ID
     * @return PaymentSource[]
     */
    public function getAllGatewayPaymentSourcesByCustomerId(int $gatewayId = null, int $customerId = null): array
    {
        if ($gatewayId === null || $customerId === null) {
            return [];
        }

        $results = $this->_createPaymentSourcesQuery()
            ->where(['customerId' => $customerId])
            ->andWhere(['gatewayId' => $gatewayId])
            ->all();

        $sources = [];

        foreach ($results as $result) {
            $sources[] = new PaymentSource($result);
        }

        return $sources;
    }

    /**
     * @deprecated in 4.0.0. Use [[getAllPaymentSourcesByCustomerId()]] instead.
     */
    public function getAllGatewayPaymentSourcesByUserId(): array
    {
        Craft::$app->getDeprecator()->log('PaymentSources::getAllGatewayPaymentSourcesByUserId()', 'The `PaymentSources::getAllGatewayPaymentSourcesByUserId()` is deprecated, use the `PaymentSources::getAllGatewayPaymentSourcesByCustomerId()` instead.');
        return $this->getAllPaymentSourcesByCustomerId();
    }

    /**
     * Returns a payment source by its gateways token
     *
     * @param string $token the payment gateway's token
     * @param int $gatewayId the gateway's ID
     * @return PaymentSource|null
     */
    public function getPaymentSourceByTokenAndGatewayId(string $token, int $gatewayId): ?PaymentSource
    {
        $result = $this->_createPaymentSourcesQuery()
            ->where(['token' => $token])
            ->andWhere(['gatewayId' => $gatewayId])
            ->one();

        return $result ? new PaymentSource($result) : null;
    }

    /**
     * Returns a payment source by its ID.
     *
     * @param int $sourceId the source ID
     */
    public function getPaymentSourceById(int $sourceId): ?PaymentSource
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
     */
    public function getPaymentSourceByIdAndUserId(int $sourceId, int $userId): ?PaymentSource
    {
        $result = $this->_createPaymentSourcesQuery()
            ->where(['id' => $sourceId])
            ->andWhere(['customerId' => $userId])
            ->one();

        return $result ? new PaymentSource($result) : null;
    }

    /**
     * Creates a payment source for a user in the gateway based on a payment form.
     *
     * @param int $customerId the user's ID
     * @param GatewayInterface $gateway the gateway
     * @param BasePaymentForm $paymentForm the payment form to use
     * @param string|null $sourceDescription the payment form to use
     * @return PaymentSource The saved payment source.
     * @throws InvalidConfigException
     * @throws PaymentSourceException If unable to create the payment source
     */
    public function createPaymentSource(int $customerId, GatewayInterface $gateway, BasePaymentForm $paymentForm, string $sourceDescription = null): PaymentSource
    {
        try {
            $source = $gateway->createPaymentSource($paymentForm, $customerId);
        } catch (Throwable $exception) {
            throw new PaymentSourceException($exception->getMessage());
        }

        $source->customerId = $customerId;

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

        $record->customerId = $paymentSource->customerId;
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
     * @throws Throwable in case something went wrong when deleting.
     */
    public function deletePaymentSourceById(int $id): bool
    {
        $record = PaymentSourceRecord::findOne($id);

        if ($record) {
            $gateway = Plugin::getInstance()->getGateways()->getGatewayById($record->gatewayId);

            $gateway?->deletePaymentSource($record->token);

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


    /**
     * Returns a Query object prepped for retrieving gateways.
     *
     * @return Query The query object.
     */
    private function _createPaymentSourcesQuery(): Query
    {
        return (new Query())
            ->select([
                'description',
                'gatewayId',
                'id',
                'response',
                'token',
                'customerId',
            ])
            ->from([Table::PAYMENTSOURCES]);
    }
}
