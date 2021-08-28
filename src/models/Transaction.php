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
use yii\behaviors\AttributeTypecastBehavior;

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
     * @var int Order ID
     */
    public int $orderId;

    /**
     * @var int Parent transaction ID
     */
    public int $parentId;

    /**
     * @var int User ID
     */
    public int $userId;

    /**
     * @var string Hash
     */
    public string $hash;

    /**
     * @var int Gateway ID
     */
    public int $gatewayId;

    /**
     * @var string Currency
     */
    public string $currency;

    /**
     * The payment amount in the payment currency.
     * Multiplying this by the `paymentRate`, give you the `amount`.
     *
     * @var float Payment Amount
     */
    public float $paymentAmount;

    /**
     * @var string Payment currency
     */
    public string $paymentCurrency;

    /**
     * @var float Payment Rate
     */
    public float $paymentRate;

    /**
     * @var string Transaction Type
     */
    public string $type;

    /**
     * The amount in the currency (which is the currency of the order)
     *
     * @var float Amount
     */
    public float $amount;

    /**
     * @var string Status
     */
    public string $status;

    /**
     * @var string reference
     */
    public string $reference;

    /**
     * @var string Code
     */
    public string $code;

    /**
     * @var string Message
     */
    public string $message;

    /**
     * @var string Note
     */
    public string $note = '';

    /**
     * @var mixed Response
     */
    public $response;

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
    private ?Gateway $_gateway;

    /**
     * @var Transaction
     */
    private Transaction $_parentTransaction;

    /**
     * @var Order
     */
    private Order $_order;

    /**
     * @var Transaction[]
     */
    private array $_children;


    /**
     * @inheritdoc
     */
    public function __construct($attributes = [])
    {
        // generate unique hash
        $this->hash = md5(uniqid(mt_rand(), true));

        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $primaryCurrency =  Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        if (!isset($this->currency)) {
            $this->currency = $primaryCurrency;
        }

        if (!isset($this->paymentCurrency)) {
            $this->paymentCurrency = $primaryCurrency;
        }

        parent::init();
    }

    /**
     * @return array
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => $this->currency,
            'currencyAttributes' => $this->currencyAttributes(),
            'attributeCurrencyMap' => [
                'paymentAmount' => $this->paymentCurrency,
            ]
        ];

        return $behaviors;
    }

    /**
     * @return array|string[]
     */
    public function currencyAttributes(): array
    {
        return [
            'amount',
            'paymentAmount',
            'refundableAmount'
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
     * @return bool
     */
    public function canCapture(): bool
    {
        return Plugin::getInstance()->getTransactions()->canCaptureTransaction($this);
    }

    /**
     * @return bool
     */
    public function canRefund(): bool
    {
        return Plugin::getInstance()->getTransactions()->canRefundTransaction($this);
    }

    /**
     * @return float
     */
    public function getRefundableAmount(): float
    {
        return Plugin::getInstance()->getTransactions()->refundableAmountForTransaction($this);
    }

    /**
     * @return Transaction|null
     */
    public function getParent(): ?Transaction
    {
        if (!isset($this->_parentTransaction) && $this->parentId) {
            $this->_parentTransaction = Plugin::getInstance()->getTransactions()->getTransactionById($this->parentId);
        }

        return $this->_parentTransaction;
    }

    /**
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        if (!isset($this->_order)) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * @return Gateway|null
     */
    public function getGateway(): ?Gateway
    {
        if (!isset($this->_gateway) && $this->gatewayId) {
            $this->_gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    /**
     * @param Gateway $gateway
     */
    public function setGateway(Gateway $gateway): void
    {
        $this->_gateway = $gateway;
    }

    /**
     * Returns child transactions.
     *
     * @return Transaction[]
     */
    public function getChildTransactions(): array
    {
        if (!isset($this->_children)) {
            $this->_children = Plugin::getInstance()->getTransactions()->getChildrenByTransactionId($this->id);
        }

        return $this->_children;
    }

    /**
     * Adds a child transaction.
     *
     * @param Transaction $transaction
     */
    public function addChildTransaction(Transaction $transaction): void
    {
        if (!isset($this->_children)) {
            $this->_children = [];
        }

        $this->_children[] = $transaction;
    }

    /**
     * Sets child transactions.
     *
     * @param array $transactions
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
