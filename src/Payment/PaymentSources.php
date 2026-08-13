<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment;

use craft\commerce\errors\PaymentSourceException;
use craft\commerce\Plugin;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Payment\Events\PaymentSourceEvent;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Models\PaymentSource;
use CraftCms\Commerce\Payment\Records\PaymentSource as PaymentSourceRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use yii\base\InvalidConfigException;
use function CraftCms\Cms\t;

#[Singleton]
class PaymentSources
{
    public const string EVENT_DELETE_PAYMENT_SOURCE = 'deletePaymentSource';

    public const string EVENT_BEFORE_SAVE_PAYMENT_SOURCE = 'beforeSavePaymentSource';

    public const string EVENT_AFTER_SAVE_PAYMENT_SOURCE = 'afterSavePaymentSource';

    /**
     * Returns a customer's payment sources, per the customer's ID.
     *
     * @return Collection<int, PaymentSource>
     */
    public function getAllPaymentSourcesByCustomerId(?int $customerId = null, ?int $gatewayId = null): Collection
    {
        if ($customerId === null) {
            return collect();
        }

        $query = $this->query()
            ->join(Table::GATEWAYS . ' as gateways', 'gateways.id', '=', 'ps.gatewayId')
            ->where('ps.customerId', $customerId);

        if ($gatewayId) {
            $query->where('ps.gatewayId', $gatewayId);
        }

        return $query->get()->map(fn($row) => new PaymentSource((array)$row));
    }

    /**
     * Returns all payment sources for a gateway.
     *
     * @return Collection<int, PaymentSource>
     */
    public function getAllPaymentSourcesByGatewayId(?int $gatewayId = null): Collection
    {
        if ($gatewayId === null) {
            return collect();
        }

        return $this->query()
            ->where('ps.gatewayId', $gatewayId)
            ->get()
            ->map(fn($row) => new PaymentSource((array)$row));
    }

    /**
     * Returns a customer's payment sources on a gateway, per the customer/user's ID.
     *
     * @return Collection<int, PaymentSource>
     */
    public function getAllGatewayPaymentSourcesByCustomerId(?int $gatewayId = null, ?int $customerId = null): Collection
    {
        if ($gatewayId === null || $customerId === null) {
            return collect();
        }

        return $this->query()
            ->where('ps.customerId', $customerId)
            ->where('ps.gatewayId', $gatewayId)
            ->get()
            ->map(fn($row) => new PaymentSource((array)$row));
    }

    /**
     * Returns a payment source by its gateway's token.
     */
    public function getPaymentSourceByTokenAndGatewayId(string $token, int $gatewayId): ?PaymentSource
    {
        $result = $this->query()
            ->where('ps.token', $token)
            ->where('ps.gatewayId', $gatewayId)
            ->first();

        return $result ? new PaymentSource((array)$result) : null;
    }

    /**
     * Returns a payment source by its ID.
     */
    public function getPaymentSourceById(int $sourceId): ?PaymentSource
    {
        // Join on gateways to ensure it is still a valid gateway payment source
        $result = $this->query()
            ->join(Table::GATEWAYS . ' as gateways', 'gateways.id', '=', 'ps.gatewayId')
            ->where('ps.id', $sourceId)
            ->first();

        return $result ? new PaymentSource((array)$result) : null;
    }

    /**
     * Returns a payment source by its ID and user ID.
     */
    public function getPaymentSourceByIdAndUserId(int $sourceId, int $userId): ?PaymentSource
    {
        $result = $this->query()
            ->where('ps.id', $sourceId)
            ->where('ps.customerId', $userId)
            ->first();

        return $result ? new PaymentSource((array)$result) : null;
    }

    /**
     * Creates a payment source for a user in the gateway based on a payment form.
     *
     * @throws PaymentSourceException If unable to create the payment source
     */
    public function createPaymentSource(int $customerId, GatewayInterface $gateway, BasePaymentForm $paymentForm, ?string $sourceDescription = null, bool $makePrimarySource = false): PaymentSource
    {
        $source = $gateway->createPaymentSource($paymentForm, $customerId);

        $source->customerId = $customerId;

        if (!empty($sourceDescription)) {
            $source->description = $sourceDescription;
        }

        if (!$this->savePaymentSource($source)) {
            throw new PaymentSourceException(t('Could not create the payment source.', category: 'commerce'));
        }

        if ($makePrimarySource) {
            Plugin::getInstance()->getCustomers()->savePrimaryPaymentSourceId($source->getCustomer(), $source->id);
        }

        return $source;
    }

    /**
     * Saves a payment source.
     *
     * @throws InvalidConfigException if the payment source couldn't be found
     */
    public function savePaymentSource(PaymentSource $paymentSource, bool $runValidation = true): bool
    {
        if ($paymentSource->id) {
            $record = PaymentSourceRecord::find($paymentSource->id);

            if (!$record) {
                throw new InvalidConfigException(t('No payment source exists with the ID "{id}"', ['id' => $paymentSource->id], category: 'commerce'));
            }
        } else {
            $record = new PaymentSourceRecord();
        }

        // Raise 'beforeSavePaymentSource' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPaymentSources()->hasEventHandlers(self::EVENT_BEFORE_SAVE_PAYMENT_SOURCE)) {
            $event = new PaymentSourceEvent(paymentSource: $paymentSource);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPaymentSources()->trigger(self::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, $event);
        }

        if ($runValidation && !$paymentSource->validate()) {
            Log::info('Payment source not saved due to validation error.');

            return false;
        }

        $record->customerId = $paymentSource->customerId;
        $record->gatewayId = $paymentSource->gatewayId;
        $record->token = $paymentSource->token;
        $record->description = $paymentSource->description;
        $record->response = $paymentSource->response;

        $record->save();

        $paymentSource->id = $record->id;

        // Raise 'afterSavePaymentSource' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPaymentSources()->hasEventHandlers(self::EVENT_AFTER_SAVE_PAYMENT_SOURCE)) {
            $event = new PaymentSourceEvent(paymentSource: $paymentSource);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPaymentSources()->trigger(self::EVENT_AFTER_SAVE_PAYMENT_SOURCE, $event);
        }

        return true;
    }

    /**
     * Delete a payment source by its ID.
     */
    public function deletePaymentSourceById(int $id): bool
    {
        $record = PaymentSourceRecord::find($id);

        if ($record) {
            $gateway = Plugin::getInstance()->getGateways()->getGatewayById($record->gatewayId);

            $gateway?->deletePaymentSource($record->token);

            $paymentSource = $this->getPaymentSourceById($id);

            // Raise 'deletePaymentSource' event
            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getPaymentSources()->hasEventHandlers(self::EVENT_DELETE_PAYMENT_SOURCE)) {
                $event = new PaymentSourceEvent(paymentSource: $paymentSource);
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getPaymentSources()->trigger(self::EVENT_DELETE_PAYMENT_SOURCE, $event);
            }

            return (bool)$record->delete();
        }

        return false;
    }

    private function query(): Builder
    {
        return DB::table(Table::PAYMENTSOURCES . ' as ps')
            ->select([
                'ps.description',
                'ps.gatewayId',
                'ps.id',
                'ps.response',
                'ps.token',
                'ps.customerId',
            ]);
    }
}
