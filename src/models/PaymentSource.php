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
     * @var int Payment source ID
     */
    public $id;

    /**
     * @var int The user ID
     */
    public $userId;

    /**
     * @var int The gateway ID.
     */
    public $gatewayId;

    /**
     * @var string Token
     */
    public $token;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var string Response data
     */
    public $response;

    /**
     * @var User|null $_user
     */
    private $_user;

    /**
     * @var GatewayInterface|null $_gateway
     */
    private $_gateway;


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
        if (null === $this->_user) {
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
        if (null === $this->_gateway) {
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
