<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\Model;
use craft\commerce\behaviors\CustomerBehavior;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\elements\User;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

/**
 * Payment source model
 *
 * @property GatewayInterface $gateway the gateway associated with this payment source
 * @property User $user the user element associated with this payment source
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentSource extends Model
{
    /**
     * @var int|null Payment source ID
     */
    public ?int $id = null;

    /**
     * @var int The customer element ID
     */
    public int $customerId;

    /**
     * @var int The gateway ID.
     */
    public int $gatewayId;

    /**
     * @var string Token
     */
    public string $token;

    /**
     * @var string Description
     */
    public string $description;

    /**
     * @var string Response data
     */
    public string $response;

    /**
     * @var User|null $_user
     */
    private ?User $_customer = null;

    /**
     * @var GatewayInterface|null $_gateway
     */
    private ?GatewayInterface $_gateway = null;


    /**
     * Returns the payment source token.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->token;
    }

    /**
     * Returns the user element associated with this payment source.
     *
     * @return User|null
     */
    public function getCustomer(): ?User
    {
        if (!isset($this->_customer)) {
            $this->_customer = Craft::$app->getUsers()->getUserById($this->customerId);
        }

        return $this->_customer;
    }

    /**
     * @return bool
     * @since 4.2
     */
    public function isPrimary(): bool
    {
        /** @var User|CustomerBehavior|null $customer */
        $customer = $this->getCustomer();
        return $customer && $customer->primaryPaymentSourceId === $this->id;
    }

    /**
     * @deprecated in 4.0.0. Use [[getCustomer()]] instead.
     */
    public function getUser(): ?User
    {
        Craft::$app->getDeprecator()->log('PaymentSource::getUser()', 'The `PaymentSource::getUser()` is deprecated, use the `PaymentSource::getCustomer()` instead.');
        return $this->getCustomer();
    }

    /**
     * Returns the gateway associated with this payment source.
     *
     * @return GatewayInterface|null
     * @throws InvalidConfigException
     */
    public function getGateway(): ?GatewayInterface
    {
        if ($this->_gateway === null && $this->gatewayId) {
            $this->_gateway = Commerce::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['token'], UniqueValidator::class, 'targetAttribute' => ['gatewayId', 'token'], 'targetClass' => PaymentSourceRecord::class],
            [['gatewayId', 'customerId', 'token', 'description'], 'required'],
        ];
    }
}
