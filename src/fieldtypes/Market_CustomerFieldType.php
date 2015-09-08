<?php
namespace Craft;

class Market_CustomerFieldType extends BaseFieldType
{
    // Properties
    // =========================================================================

    /** @var  Market_CustomerModel $_customer */
    private $_customer;

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Commerce Customer Info');
    }

    public function defineContentAttribute()
    {
        return false;
    }

    public function getInputHtml($name,$value)
    {
        return craft()->templates->render('market/_fieldtypes/customer/_input', [
            'customer' => $this->getCustomer()
        ]);
    }

    public function prepValue($value)
    {
        return $value
    }

    private function getCustomer()
    {
        if (!$this->_customer){
            $this->_customer = craft()->market_customer->getByUserId($this->element->id);
        }

        return $this->_customer;
    }
}
