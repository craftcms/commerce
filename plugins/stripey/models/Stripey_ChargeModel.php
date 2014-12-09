<?php
namespace Craft;

/**
 * Class Stripey_ChargeFormModel
 *
 * This Model is responsible for passing around a
 * form object which is used to make a new charge.
 *
 * @package Craft
 *
 * @property AttributeType::Number $id Craft id
 * @property AttributeType::String $stripeId Stripe Id
 * @property AttributeType::Number $amount Amount to Charge
 * @property AttributeType::Enum $currency Currency to charge in
 * @property AttributeType::String $card Stripe card token
 * @property AttributeType::String $customer Stripe customer token
 * @property AttributeType::String $description Description stored in Stripe
 */
class Stripey_ChargeModel extends BaseElementModel
{
    protected $elementType = 'Stripey_Charge';
    protected $modelRecord = 'Stripey_ChargeRecord';

    private $_apiData;

    /**
     * @inheritDoc BaseRecord::defineAttributes()
     *
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), array(
            'stripeId'    => AttributeType::String,
            'amount'      => AttributeType::Number,
            //TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
            'currency'    => array(AttributeType::Enum, 'values' => "AUD,USD"),
            'card'        => AttributeType::String,
            'customer'    => AttributeType::String,
            'description' => AttributeType::String
        ));
    }


    /**
     * Returns whether the current user can edit the element.
     *
     * @return bool
     */
    public function isEditable()
    {
        return true;
    }


    /**
     * @return mixed|string
     */
    public function __toString()
    {
        return $this->stripeId;
    }


    /**
     * Returns the element's CP edit URL.
     *
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/charges/'.$this->id);
    }

    /**
     * @return mixed
     */
    public function getCreated()
    {
        $this->_apiData = stripey()->api->stripe->charges()->find(array(
            'id' => $this->stripeId
        ));
        return $this->_apiData->created;
    }

}