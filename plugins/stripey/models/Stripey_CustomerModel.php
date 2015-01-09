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
 */
class Stripey_CustomerModel extends BaseElementModel
{
    protected $elementType = 'Stripey_Customer';
    protected $modelRecord = 'Stripey_CustomerRecord';

    private $_apiData = null;

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
        // This is used in the elementType index template as the linked text column
        return $this->id;
    }

    /**
     * Returns the element's CP edit URL.
     *
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/customer/' . $this->id);
    }


    public function getData()
    {
        if ($this->_apiData == null) {
            $this->_loadStripeData();
        }

        return $this;
    }

    private function _loadStripeData()
    {
        $this->_apiData = \Stripey\Stripey::app()['stripe']->customers()->find(array(
            'id' => $this->stripeId
        ));

        foreach ($this->_apiData as $key => $val) {
            if (in_array($key, $this->attributeNames()) && $key != 'id') {
                $this->$key = $val;
            }
        }

    }

    /**
     * Charge Model Attributes
     *
     * @inheritDoc BaseRecord::defineAttributes()
     *
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), array(

            /**
             * Required fields on new charge
             */
            'stripeId'        => AttributeType::String,
            'amount'          => AttributeType::Number,
            //TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
            'currency'        => array(AttributeType::Enum, 'values' => "AUD,USD"),

            /**
             * Optional fields on new charge
             */
            'description'     => AttributeType::String,
            'email'           => AttributeType::String,
            'metadata'        => AttributeType::Mixed,

            /**
             * Only exist on a saved customer
             */
            'created'         => AttributeType::DateTime,
            'discount'        => AttributeType::Mixed,
            'account_balance' => AttributeType::Number,
            'delinquent'      => AttributeType::String,
            'livemode'        => AttributeType::Bool,
        ));
    }


}