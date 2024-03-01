<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Gateway;
use craft\commerce\base\Model;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Class Transaction
 *
 * @property array|Transaction[] $childTransactions child transactions
 * @property Gateway $gateway
 * @property Order $order
 * @property Transaction $parent
 * @property-read float $refundableAmount
 * @property-read string $amountAsCurrency
 * @property-read string $paymentAmountAsCurrency
 * @property-read string $refundableAmountAsCurrency
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Transaction extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int|null Order ID
     */
    public ?int $orderId = null;

    /**
     * @var int|null Parent transaction ID
     */
    public ?int $parentId = null;

    /**
     * This is the user who made the transaction. It could be the customer if logged in, or a store administrator.
     *
     * @var int|null User ID
     */
    public ?int $userId = null;

    /**
     * @var string|null Hash
     */
    public ?string $hash = null;

    /**
     * @var int|null Gateway ID
     */
    public ?int $gatewayId = null;

    /**
     * @var string|null Currency
     */
    public ?string $currency = null;

    /**
     * The payment amount in the payment currency.
     * Multiplying this by the `paymentRate`, give you the `amount`.
     *
     * @var float Payment Amount
     */
    public float $paymentAmount;

    /**
     * @var string|null Payment currency
     */
    public ?string $paymentCurrency = null;

    /**
     * @var float Payment Rate
     */
    public float $paymentRate;

    /**
     * @var string|null Transaction Type
     */
    public ?string $type = null;

    /**
     * The amount in the currency (which is the currency of the order)
     *
     * @var float Amount
     */
    public float $amount;

    /**
     * @var string|null Status
     */
    public ?string $status = null;

    /**
     * @var string|null reference
     */
    public ?string $reference = null;

    /**
     * @var string|null Code
     */
    public ?string $code = null;

    /**
     * @var string|null Message
     */
    public ?string $message = null;

    /**
     * @var string Note
     */
    public string $note = '';

    /**
     * @var mixed Response
     */
    public mixed $response = null;

    /**
     * @var DateTime|null The date that the transaction was created
     */
    public ?DateTIme $dateCreated = null;

    /**
     * @var DateTime|null The date that the transaction was last updated
     */
    public ?DateTIme $dateUpdated = null;

    /**
     * @var Gateway|null
     */
    private ?Gateway $_gateway = null;

    /**
     * @var Transaction|null
     */
    private ?Transaction $_parentTransaction = null;

    /**
     * @var Order|null
     */
    private ?Order $_order = null;

    /**
     * @var Transaction[]|null
     */
    private ?array $_children = null;


    /**
     * @inheritdoc
     */
    public function __construct($attributes = [])
    {
        // generate unique hash
        $this->hash = md5(uniqid((string)mt_rand(), true));

        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $primaryCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        if (!isset($this->currency)) {
            $this->currency = $primaryCurrency;
        }

        if (!isset($this->paymentCurrency)) {
            $this->paymentCurrency = $primaryCurrency;
        }

        parent::init();
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => $this->currency,
            'currencyAttributes' => $this->currencyAttributes(),
            'attributeCurrencyMap' => [
                'paymentAmount' => $this->paymentCurrency,
            ],
        ];

        return $behaviors;
    }

    /**
     * @return array
     */
    public function currencyAttributes(): array
    {
        return [
            'amount',
            'paymentAmount',
            'refundableAmount',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        ArrayHelper::removeValue($names, 'response');
        return $names;
    }

    /**
     * @inheritDoc
     */
    public function extraFields(): array
    {
        return [
            'response',
        ];
    }

    /**
     * @throws InvalidConfigException
     */
    public function canCapture(): bool
    {
        return Plugin::getInstance()->getTransactions()->canCaptureTransaction($this);
    }

    /**
     * @throws InvalidConfigException
     */
    public function canRefund(): bool
    {
        return Plugin::getInstance()->getTransactions()->canRefundTransaction($this);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getRefundableAmount(): float
    {
        return Plugin::getInstance()->getTransactions()->refundableAmountForTransaction($this);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getParent(): ?Transaction
    {
        if (null === $this->_parentTransaction && $this->parentId) {
            $this->_parentTransaction = Plugin::getInstance()->getTransactions()->getTransactionById($this->parentId);
        }

        return $this->_parentTransaction;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getGateway(): ?Gateway
    {
        if (!isset($this->_gateway) && $this->gatewayId) {
            $this->_gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    public function setGateway(Gateway $gateway): void
    {
        $this->_gateway = $gateway;
    }

    /**
     * Returns child transactions.
     *
     * @return Transaction[]
     * @throws InvalidConfigException
     */
    public function getChildTransactions(): array
    {
        if (!isset($this->_children) && $this->id) {
            $this->_children = Plugin::getInstance()->getTransactions()->getChildrenByTransactionId($this->id);
        }

        return $this->_children ?? [];
    }

    /**
     * Adds a child transaction.
     */
    public function addChildTransaction(Transaction $transaction): void
    {
        if (null === $this->_children) {
            $this->_children = [];
        }

        $this->_children[] = $transaction;
    }

    /**
     * Sets child transactions.
     */
    public function setChildTransactions(array $transactions): void
    {
        $this->_children = $transactions;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['type', 'status', 'orderId'], 'required'],
        ];
    }
}
