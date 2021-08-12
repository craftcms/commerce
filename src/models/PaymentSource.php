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
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\PaymentSource as PaymentSourceRecord;
use craft\elements\User;
use craft\validators\UniqueValidator;

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
     * @var int The user ID
     */
    public int $userId;

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
    private ?User $_user;

    /**
     * @var GatewayInterface|null $_gateway
     */
    private ?GatewayInterface $_gateway;


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
    public function getUser()
    {
        if (!isset($this->_user)) {
            $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return $this->_user;
    }

    /**
     * Returns the gateway associated with this payment source.
     *
     * @return GatewayInterface|null
     */
    public function getGateway()
    {
        if (isset($this->_gateway)) {
            $this->_gateway = Commerce::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['token'], UniqueValidator::class, 'targetAttribute' => ['gatewayId', 'token'], 'targetClass' => PaymentSourceRecord::class];
        $rules[] = [['gatewayId', 'userId', 'token', 'description'], 'required'];

        return $rules;
    }
}
