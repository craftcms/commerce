<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Customer address model.
 *
 * @property int $id
 * @property string $firstName
 * @property string $lastName
 * @property string $address1
 * @property string $address2
 * @property string $city
 * @property string $zipCode
 * @property string $phone
 * @property string $alternativePhone
 * @property string $company
 * @property string $companyNumber
 * @property string $stateName
 * @property int $countryId
 * @property int $stateId
 * @property int $customerId
 *
 * @property Commerce_CountryModel $country
 * @property Commerce_StateModel $state
 * @property Commerce_CustomerModel $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_AddressModel extends BaseModel
{
    use Commerce_ModelRelationsTrait;

    /** @var int|string Either ID of a state or name of state if it's not present in the DB */
    public $stateValue;

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/addresses/' . $this->id);
    }

    /**
     * @return string
     */
    public function getStateText()
    {
        return $this->stateName ? ($this->stateId ? $this->state->name : '') : '';
    }

    /**
     * @return string
     */
    public function getCountryText()
    {
        return $this->countryId ? craft()->commerce_countries->getCountryById($this->countryId)->name : '';
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'firstName' => AttributeType::String,
            'lastName' => AttributeType::String,
            'address1' => AttributeType::String,
            'address2' => AttributeType::String,
            'city' => AttributeType::String,
            'zipCode' => AttributeType::String,
            'phone' => AttributeType::String,
            'alternativePhone' => AttributeType::String,
            'company' => AttributeType::String,
            'companyNumber' => AttributeType::String,
            'stateName' => AttributeType::String,
            'countryId' => AttributeType::Number,
            'stateId' => AttributeType::Number,
            'customerId' => AttributeType::Number
        ];
    }
}
