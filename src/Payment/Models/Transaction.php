<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Models;

use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Services\Orders;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Payment\Transactions;
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

        $primaryCurrency = app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso();
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
        return app(Transactions::class)->canCaptureTransaction($this);
    }

    public function canRefund(): bool
    {
        return app(Transactions::class)->canRefundTransaction($this);
    }

    public function getRefundableAmount(): float
    {
        return app(Transactions::class)->refundableAmountForTransaction($this);
    }

    public function getParent(): ?Transaction
    {
        if ($this->_parentTransaction === null && $this->parentId) {
            $this->_parentTransaction = app(Transactions::class)->getTransactionById($this->parentId);
        }

        return $this->_parentTransaction;
    }

    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && $this->orderId) {
            $this->_order = app(Orders::class)->getOrderById($this->orderId);
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
            $this->_gateway = app(Gateways::class)->getGatewayById($this->gatewayId);
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
            $this->_children = app(Transactions::class)->getChildrenByTransactionId($this->id);
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
