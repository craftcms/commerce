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
use DvK\Vat\Validator;
use Exception;

/**
 * Address Model
 *
 * @property Country $country
 * @property string $countryText
 * @property string $cpEditUrl
 * @property string $fullName
 * @property State $state
 * @property string $stateText
 * @property string $abbreviationText
 * @property int|string $stateValue
 * @property Validator $vatValidator
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Address extends Model
{
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
     * @var string Full Name
     * @since 2.2
     */
    public $fullName;

    /**
     * @var string Address Line 1
     */
    public $address1;

    /**
     * @var string Address Line 2
     */
    public $address2;

    /**
     * @var string Address Line 3
     * @since 2.2
     */
    public $address3;

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
     * @var string Label
     * @since 2.2
     */
    public $label;

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
     * @var int State ID
     */
    public $stateId;

    /**
     * @var string Notes
     * @since 2.2
     */
    public $notes;

    /**
     * @var string Custom Field 1
     * @since 2.2
     */
    public $custom1;

    /**
     * @var string Custom Field 2
     * @since 2.2
     */
    public $custom2;

    /**
     * @var string Custom Field 3
     * @since 2.2
     */
    public $custom3;

    /**
     * @var string Custom Field 4
     * @since 2.2
     */
    public $custom4;

    /**
     * @var bool If this address is used for estimated values
     * @since 2.2
     */
    public $isEstimated = false;

    /**
     * @var int|string Can be a State ID or State Name
     */
    private $_stateValue;

    /**
     * @var
     */
    private $_vatValidator;


    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/addresses/' . $this->id);
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
        $names[] = 'abbreviationText';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return [
            'country',
            'state',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();
        $labels['firstName'] = Plugin::t('First Name');
        $labels['lastName'] = Plugin::t('Last Name');
        $labels['fullName'] = Plugin::t('Full Name');
        $labels['attention'] = Plugin::t('Attention');
        $labels['title'] = Plugin::t('Title');
        $labels['address1'] = Plugin::t('Address 1');
        $labels['address2'] = Plugin::t('Address 2');
        $labels['address3'] = Plugin::t('Address 3');
        $labels['city'] = Plugin::t('City');
        $labels['zipCode'] = Plugin::t('Zip Code');
        $labels['phone'] = Plugin::t('Phone');
        $labels['alternativePhone'] = Plugin::t('Alternative Phone');
        $labels['businessName'] = Plugin::t('Business Name');
        $labels['businessId'] = Plugin::t('Business ID');
        $labels['businessTaxId'] = Plugin::t('Business Tax ID');
        $labels['countryId'] = Plugin::t('Country');
        $labels['stateId'] = Plugin::t('State');
        $labels['stateName'] = Plugin::t('State');
        $labels['stateValue'] = Plugin::t('State');
        $labels['custom1'] = Plugin::t('Custom 1');
        $labels['custom2'] = Plugin::t('Custom 2');
        $labels['custom3'] = Plugin::t('Custom 3');
        $labels['custom4'] = Plugin::t('Custom 4');
        $labels['notes'] = Plugin::t('Notes');
        $labels['label'] = Plugin::t('Label');
        return $labels;
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['stateId'], 'validateState', 'skipOnEmpty' => false];
        $rules[] = [['businessTaxId'], 'validateBusinessTaxId', 'skipOnEmpty' => true];

        $rules[] = [[
            'firstName',
            'lastName',
            'fullName',
            'attention',
            'title',
            'address1',
            'address2',
            'address3',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessId',
            'businessTaxId',
            'countryId',
            'stateId',
            'stateName',
            'stateValue',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'notes',
            'label',
        ], 'trim'];

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
            $this->addError('stateValue', Plugin::t('Country requires a related state selected.'));
        }
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     */
    public function validateBusinessTaxId($attribute, $params, $validator)
    {
        if (!Plugin::getInstance()->getSettings()->validateBusinessTaxIdAsVatId) {
            return;
        }

        // Do we have a valid VAT ID in our cache?
        $validBusinessTaxId = Craft::$app->getCache()->exists('commerce:validVatId:' . $this->businessTaxId);

        // If we do not have a valid VAT ID in cache, see if we can get one from the API
        if (!$validBusinessTaxId) {
            $validBusinessTaxId = $this->_validateVatNumber($this->businessTaxId);
        }

        if ($validBusinessTaxId) {
            Craft::$app->getCache()->set('commerce:validVatId:' . $this->businessTaxId, '1');
        }

        // Clean up if the API returned false and the item was still in cache
        if (!$validBusinessTaxId) {
            Craft::$app->getCache()->delete('commerce:validVatId:' . $this->businessTaxId);
            $this->addError('businessTaxId', Plugin::t('Invalid Business Tax ID.'));
        }
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
     * @return string
     */
    public function getAbbreviationText(): string
    {
        return $this->stateId ? $this->getState()->abbreviation : '';
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
     * Sets the stateId or stateName based on the stateValue set.
     *
     * @param string|int $value A state ID or a state name.
     */
    public function setStateValue($value)
    {
        if ($value) {
            if (Plugin::getInstance()->getStates()->getStateById((int)$value)) {
                $this->stateId = $value;
            } else {
                $this->stateId = null;
                $this->stateName = $value;
            }

            $this->_stateValue = $value;
        } else {
            $this->stateId = null;
            $this->stateName = null;
            $this->_stateValue = null;
        }
    }

    /**
     * @param string $businessVatId
     * @return bool
     */
    private function _validateVatNumber($businessVatId)
    {
        try {
            return $this->_getVatValidator()->validate($businessVatId);
        } catch (Exception $e) {
            Craft::error('Communication with VAT API failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * @return Validator
     */
    private function _getVatValidator(): Validator
    {
        if ($this->_vatValidator === null) {
            $this->_vatValidator = new Validator();
        }

        return $this->_vatValidator;
    }
}
