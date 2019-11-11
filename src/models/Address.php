<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\events\RegisterAddressRulesEvent;
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
    // Constants
    // =========================================================================

    /**
     * @event RegisterAddressRulesEvent The event that is raised after initial rules were set.
     *
     * Plugins can add additional address validation rules.
     *
     * ```php
     * use craft\commerce\events\RegisterAddressRulesEvent;
     * use craft\commerce\models\Address;
     *
     * Event::on(Address::class, Address::EVENT_REGISTER_ADDRESS_VALIDATION_RULES, function(RegisterAddressRulesEvent $event) {
     *      $event->rules[] = [['attention'], 'required'];
     * });
     * ```
     */
    const EVENT_REGISTER_ADDRESS_VALIDATION_RULES = 'registerAddressValidationRules';

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
     * @var int Country ID
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

    // Public Methods
    // =========================================================================

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
        $labels['firstName'] = Craft::t('commerce', 'First Name');
        $labels['lastName'] = Craft::t('commerce', 'Last Name');
        $labels['fullName'] = Craft::t('commerce', 'Full Name');
        $labels['attention'] = Craft::t('commerce', 'Attention');
        $labels['title'] = Craft::t('commerce', 'Title');
        $labels['address1'] = Craft::t('commerce', 'Address 1');
        $labels['address2'] = Craft::t('commerce', 'Address 2');
        $labels['address3'] = Craft::t('commerce', 'Address 3');
        $labels['city'] = Craft::t('commerce', 'City');
        $labels['zipCode'] = Craft::t('commerce', 'Zip Code');
        $labels['phone'] = Craft::t('commerce', 'Phone');
        $labels['alternativePhone'] = Craft::t('commerce', 'Alternative Phone');
        $labels['businessName'] = Craft::t('commerce', 'Business Name');
        $labels['businessId'] = Craft::t('commerce', 'Business ID');
        $labels['businessTaxId'] = Craft::t('commerce', 'Business Tax ID');
        $labels['countryId'] = Craft::t('commerce', 'Country');
        $labels['stateId'] = Craft::t('commerce', 'State');
        $labels['stateName'] = Craft::t('commerce', 'State');
        $labels['stateValue'] = Craft::t('commerce', 'State');
        $labels['custom1'] = Craft::t('commerce', 'Custom 1');
        $labels['custom2'] = Craft::t('commerce', 'Custom 2');
        $labels['custom3'] = Craft::t('commerce', 'Custom 3');
        $labels['custom4'] = Craft::t('commerce', 'Custom 4');
        $labels['notes'] = Craft::t('commerce', 'Notes');
        $labels['label'] = Craft::t('commerce', 'Label');
        return $labels;
    }

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['firstName'], 'required'];
        $rules[] = [['lastName'], 'required'];
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

        $event = new RegisterAddressRulesEvent([
            'rules' => $rules
        ]);

        //Raise the RegisterAddressRules event
        if ($this->hasEventHandlers(self::EVENT_REGISTER_ADDRESS_VALIDATION_RULES)) {
            $this->trigger(self::EVENT_REGISTER_ADDRESS_VALIDATION_RULES, $event);
        }

        return $event->rules;
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
            $this->addError('businessTaxId', Craft::t('commerce', 'Invalid Business Tax ID.'));
        }
    }

    /**
     * Returns the address full name.
     *
     * @return string|null
     * @deprecated 2.2.7 in favor of using the fullName attribute
     */
    public function getFullName()
    {
        Craft::$app->getDeprecator()->log('Address::getFullName()', 'Address::getFullName() has been deprecated. Use fullName attribute instead.');

        // Return the full name if it is set explicitly
        if ($this->fullName) {
            return $this->fullName;
        }

        $firstName = trim($this->firstName);
        $lastName = trim($this->lastName);

        if (!$firstName && !$lastName) {
            return null;
        }

        $name = $firstName;

        if ($firstName && $lastName) {
            $name .= ' ';
        }

        $name .= $lastName;

        return $name;
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
