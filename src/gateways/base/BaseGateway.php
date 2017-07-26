<?php

namespace craft\commerce\gateways\base;

use Craft;
use craft\base\SavableComponent;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\helpers\UrlHelper;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;
use Omnipay\Common\GatewayInterface as OmnipayGatewayInterface;
use Omnipay\Manual\Message\Request;
use Omnipay\Omnipay;

/**
 * Class Payment Method Model
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
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
abstract class BaseGateway extends SavableComponent implements GatewayInterface
{
    use GatewayTrait;

    /**
     * @var AbstractGateway
     */
    private $_gateway;

    /**
     * Return the OmniPay gateway class name.
     *
     * @return string|null
     */
    abstract protected function getGatewayClassName();

    /**
     * Payment Form HTML
     *
     * @param array $params
     *
     * @return string|null
     */
    abstract public function getPaymentFormHtml(array $params);

    /**
     * Payment Form HTML
     *
     * @return BasePaymentForm|null
     */
    abstract public function getPaymentFormModel();

    /**
     * @param CreditCard            $card
     * @param CreditCardPaymentForm $paymentForm
     *
     * @return void
     */
    abstract public function populateCard(CreditCard $card, CreditCardPaymentForm $paymentForm);

    /**
     * @param Request         $request
     * @param BasePaymentForm $form
     *
     * @return void
     */
    abstract public function populateRequest(Request $request, BasePaymentForm $form);

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
        return UrlHelper::cpUrl('commerce/settings/gateways/'.$this->id);
    }

    /**
     * Whether this payment method requires credit card details.
     *
     * @return bool
     */
    public function requiresCreditCard()
    {
        return true;
    }

    /**
     * Return the payment type options.
     * 
     * @return array
     */
    public function getPaymentTypeOptions()
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function purchase()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function authorize()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function refund()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund()
    {
        return false;
    }


    /**
     * @inheritdoc
     */
    public function capture()
    {
        return false;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns an Omnipay gateway instance based on the stored settings.
     *
     * @return OmnipayGatewayInterface The Omnipay gateway.
     */
    protected function createGateway()
    {
        $client = Craft::createGuzzleClient();
        return Omnipay::create($this->getGatewayClassName(), $client);
    }

    /**
     * @return OmnipayGatewayInterface
     */
    protected function gateway()
    {
        if ($this->_gateway !== null) {
            return $this->_gateway;
        }

        return $this->_gateway = $this->createGateway();
    }

}
