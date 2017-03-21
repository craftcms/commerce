<?php
namespace craft\commerce\models;

use Commerce\Gateways\BaseGatewayAdapter;
use Commerce\Interfaces\PaymentForm;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Class Commerce_PaymentMethodModel
 *
 * @package   Craft
 *
 * @property int                $id
 * @property string             $class
 * @property string             $name
 * @property string             $paymentType
 * @property array              $settings
 * @property bool               $frontendEnabled
 * @property bool               $isArchived
 * @property bool               $dateArchived
 *
 *
 * @property BaseGatewayAdapter $gateway
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class PaymentMethod extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Class
     */
    public $class;

    /**
     * @var string Class
     */
    public $name;

    /**
     * @var string Payment Type
     */
    public $paymentType;

    /**
     * @var bool Enabled on the frontend
     */
    public $frontendEnabled;

    /**
     * @var bool Archived
     */
    public $isArchived;

    /**
     * @var \DateTime Archived Date
     */
    public $dateArchived;

    /**
     * @var mixed Settings
     */
    public $settings;

    /** @var BaseGatewayAdapter */
    private $_gatewayAdapter;

//'id' => AttributeType::Number,
//'class' => AttributeType::String,
//'name' => AttributeType::String,
//'paymentType' => [
//AttributeType::Enum,
//'values' => ['authorize', 'purchase'],
//'required' => true,
//'default' => 'purchase'
//],
//'frontendEnabled' => [AttributeType::Bool, 'default' => true],
//'isArchived' => [AttributeType::Bool, 'default' => false],
//'dateArchived' => [AttributeType::DateTime],
//'settings' => [AttributeType::Mixed],
//];

    /**
     * Populates a new model instance with a given set of attributes.
     *
     * @param mixed $values
     *
     * @return BaseModel
     */
    public static function populateModel($values)
    {
        $paymentMethod = parent::populateModel($values);

        if ($paymentMethod->id) {
            // Are its settings being set from the config file?
            $paymentMethodSettings = craft()->config->get('paymentMethodSettings', 'commerce');

            if (isset($paymentMethodSettings[$paymentMethod->id])) {
                $paymentMethod->settings = array_merge($paymentMethod->settings, $paymentMethodSettings[$paymentMethod->id]);
            }
        }

        return $paymentMethod;
    }

    /**
     * @inheritdoc
     */
    public function rule()
    {
        return [
            [['paymentType'], 'required']
        ];
    }

    /**
     * Initialize
     */
    public function init()
    {
        $this->_initDefaults();
    }

    /**
     * Initialize default values
     */
    private function _initDefaults()
    {
        $this->paymentType = 'purchase';
    }

    /**
     * Returns the name of this payment method.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/paymentmethods/'.$this->id);
    }

    /**
     * @return mixed
     */
    public function getGateway()
    {
        if ($gatewayAdapter = $this->getGatewayAdapter()) {
            return $gatewayAdapter->getGateway();
        }
    }

    /**
     * @return BaseGatewayAdapter|null
     */
    public function getGatewayAdapter()
    {
        $gateways = Plugin::getInstance()->getGateways()->getAllGateways();
        if (!empty($this->class) && !$this->_gatewayAdapter) {
            if (array_key_exists($this->class, $gateways)) {
                $this->_gatewayAdapter = $gateways[$this->class];
                $this->_gatewayAdapter->setAttributes($this->settings);
                $this->_gatewayAdapter->setPaymentMethod($this);
            }
        }

        return $this->_gatewayAdapter;
    }

    /**
     * Whether this payment method requires credit card details
     *
     * @return bool
     */
    public function requiresCard()
    {
        if ($gatewayAdapter = $this->getGatewayAdapter()) {
            return $gatewayAdapter->requiresCreditCard();
        }
    }

    /**
     * Payment Form HTML
     *
     * @return bool
     */
    public function getPaymentFormHtml($params)
    {
        if ($gatewayAdapter = $this->getGatewayAdapter()) {
            return $gatewayAdapter->getPaymentFormHtml($params);
        }
    }

    /**
     * Payment Form HTML
     *
     * @return PaymentForm
     */
    public function getPaymentFormModel()
    {
        if ($gatewayAdapter = $this->getGatewayAdapter()) {
            return $gatewayAdapter->getPaymentFormModel();
        }
    }

    /**
     * @param $card
     * @param $paymentForm
     *
     */
    public function populateCard($card, $paymentForm)
    {
        if ($gatewayAdapter = $this->getGatewayAdapter()) {
            $gatewayAdapter->populateCard($card, $paymentForm);
        }
    }

    /**
     * @param $request
     * @param $form
     *
     */
    public function populateRequest($request, $form)
    {
        if ($gatewayAdapter = $this->getGatewayAdapter()) {
            $gatewayAdapter->populateRequest($request, $form);
        }
    }

    /**
     * @return array
     */
    public function getPaymentTypeOptions()
    {
        return [
            'authorize' => Craft::t('Authorize Only (Manually Capture)'),
            'purchase' => Craft::t('Purchase (Authorize and Capture Immediately)'),
        ];
    }
}
