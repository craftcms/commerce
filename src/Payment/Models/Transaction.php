<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Models;

use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use DateTime;

class Transaction extends Component
{
    public ?int $id = null;

    public ?int $orderId = null;

    public ?int $parentId = null;

    public ?int $userId = null;

    public ?string $hash = null;

    public ?int $gatewayId = null;

    public ?string $currency = null;

    public float $paymentAmount;

    public ?string $paymentCurrency = null;

    public float $paymentRate;

    public ?string $type = null;

    public float $amount;

    public ?string $status = null;

    public ?string $reference = null;

    public ?string $code = null;

    public ?string $message = null;

    public string $note = '';

    public mixed $response = null;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?Gateway $_gateway = null;

    private ?Transaction $_parentTransaction = null;

    private ?Order $_order = null;

    private ?array $_children = null;

    public function __construct(array|object $config = [])
    {
        $this->hash = md5(uniqid((string)mt_rand(), true));

        // TODO: migrate to app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso() once service migrated to src/
        /** @phpstan-ignore-next-line */
        $primaryCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $this->currency ??= $primaryCurrency;
        $this->paymentCurrency ??= $primaryCurrency;

        parent::__construct($config);
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'type' => ['required', 'string'],
            'status' => ['required', 'string'],
            'orderId' => ['required', 'integer'],
        ];
    }

    public function canCapture(): bool
    {
        // TODO: migrate to app(Transactions::class)->canCaptureTransaction() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getTransactions()->canCaptureTransaction($this);
    }

    public function canRefund(): bool
    {
        // TODO: migrate to app(Transactions::class)->canRefundTransaction() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getTransactions()->canRefundTransaction($this);
    }

    public function getRefundableAmount(): float
    {
        // TODO: migrate to app(Transactions::class)->refundableAmountForTransaction() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getTransactions()->refundableAmountForTransaction($this);
    }

    public function getParent(): ?Transaction
    {
        if ($this->_parentTransaction === null && $this->parentId) {
            // TODO: migrate to app(Transactions::class)->getTransactionById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_parentTransaction = Plugin::getInstance()->getTransactions()->getTransactionById($this->parentId);
        }

        return $this->_parentTransaction;
    }

    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && $this->orderId) {
            // TODO: migrate to app(Orders::class)->getOrderById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        /** @phpstan-ignore-next-line */
        $this->orderId = $order->id;
    }

    public function getGateway(): ?Gateway
    {
        if (!isset($this->_gateway) && $this->gatewayId) {
            // TODO: migrate to app(Gateways::class)->getGatewayById() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    public function setGateway(Gateway $gateway): void
    {
        $this->_gateway = $gateway;
    }

    public function getChildTransactions(): array
    {
        if (!isset($this->_children) && $this->id) {
            // TODO: migrate to app(Transactions::class)->getChildrenByTransactionId() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $this->_children = Plugin::getInstance()->getTransactions()->getChildrenByTransactionId($this->id);
        }

        return $this->_children ?? [];
    }

    public function addChildTransaction(Transaction $transaction): void
    {
        if ($this->_children === null) {
            $this->_children = [];
        }

        $this->_children[] = $transaction;
    }

    public function setChildTransactions(array $transactions): void
    {
        $this->_children = $transactions;
    }
}
