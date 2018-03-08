<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Address Model
 *
 * @property Country $country
 * @property string $countryText
 * @property string $cpEditUrl
 * @property string $fullName
 * @property State $state
 * @property string $stateText
 * @property int|string $stateValue
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Address extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int Address ID
     */
    public $id;

    /**
     * @var bool True, if this address is the stock location.
     */
    public $isStoreLocation = false;

    /**
     * @var string Attention
     */
    public $attention;

    /**
     * @var string Title
     */
    public $title;

    /**
     * @var string First Name
     */
    public $firstName;

    /**
     * @var string Last Name
     */
    public $lastName;

    /**
     * @var string Address Line 1
     */
    public $address1;

    /**
     * @var string Address Line 2
     */
    public $address2;

    /**
     * @var string City
     */
    public $city;

    /**
     * @var string Zip
     */
    public $zipCode;

    /**
     * @var string Phone
     */
    public $phone;

    /**
     * @var string Alternative Phone
     */
    public $alternativePhone;

    /**
     * @var string Business Name
     */
    public $businessName;

    /**
     * @var string Business Tax ID
     */
    public $businessTaxId;

    /**
     * @var string Business ID
     */
    public $businessId;

    /**
     * @var string State Name
     */
    public $stateName;

    /**
     * @var int Country ID
     */
    public $countryId;

    /**
     * @var int Country ID
     */
    public $stateId;

    /**
     * @var int|string Can be a State ID or State Name
     */
    private $_stateValue;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/addresses/'.$this->id);
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'fullName';
        $names[] = 'countryText';
        $names[] = 'stateText';
        $names[] = 'stateValue';
        return $names;
    }

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['firstName'], 'required'];
        $rules[] = [['lastName'], 'required'];
        $rules[] = ['stateId', 'validateState', 'skipOnEmpty' => false];

        return $rules;
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     */
    public function validateState($attribute, $params, $validator)
    {
        $country = $this->countryId ? Plugin::getInstance()->getCountries()->getCountryById($this->countryId) : null;
        $state = $this->stateId ? Plugin::getInstance()->getStates()->getStateById($this->stateId) : null;
        if ($country && $country->isStateRequired && (!$state || ($state && $state->countryId !== $country->id))) {
            $this->addError('stateValue', Craft::t('commerce', 'Country requires a related state selected.'));
        }
    }

    /**
     * @return string
     */
    public function getFullName(): string
    {
        $firstName = trim($this->firstName);
        $lastName = trim($this->lastName);

        return $firstName.($firstName && $lastName ? ' ' : '').$lastName;
    }

    /**
     * @return string
     */
    public function getCountryText(): string
    {
        return $this->countryId ? $this->getCountry()->name : '';
    }

    /**
     * @return Country|null
     */
    public function getCountry()
    {
        return $this->countryId ? Plugin::getInstance()->getCountries()->getCountryById($this->countryId) : null;
    }

    /**
     * @return string
     */
    public function getStateText(): string
    {
        if ($this->stateName) {
            return $this->stateId ? $this->getState()->name : $this->stateName;
        }

        return $this->stateId ? $this->getState()->name : '';
    }

    /**
     * @return State|null
     */
    public function getState()
    {
        return $this->stateId ? Plugin::getInstance()->getStates()->getStateById($this->stateId) : null;
    }

    /**
     * @return int|string
     */
    public function getStateValue()
    {
        if ($this->_stateValue === null) {
            if ($this->stateName) {
                return $this->stateId ?: $this->stateName;
            }

            return $this->stateId ?: '';
        }

        return $this->_stateValue;
    }

    /**
     * @param $value
     */
    public function setStateValue($value)
    {
        $this->_stateValue = $value;
    }
}
