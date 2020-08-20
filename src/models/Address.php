<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\events\DefineAddressLinesEvent;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\validators\StringValidator;
use DvK\Vat\Validator;
use Exception;
use LitEmoji\LitEmoji;

/**
 * Address Model
 *
 * @property Country $country
 * @property string $countryText
 * @property string $cpEditUrl
 * @property State $state
 * @property string $stateText
 * @property string $abbreviationText
 * @property int|string $stateValue
 * @property string $countryIso
 * @property Validator $vatValidator
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Address extends Model
{
    /**
     * @event DefineAddressLinesEvent The event that is triggered when defining the arrayable fields
     * @see getAddressLines()
     * @since 3.2.0
     */
    const EVENT_DEFINE_ADDRESS_LINES = 'defineAddressLines';

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
     * @inheritDoc
     */
    public function init()
    {
        $this->notes = LitEmoji::shortcodeToUnicode($this->notes);

        parent::init();
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/addresses/' . $this->id);
    }

    /**
     * @inheritdoc
     *
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'stateValue';

        return $names;
    }

    /**
     * @inheritDoc
     * @since 3.2.1
     */
    public function fields()
    {
        $fields = parent::fields();
        $fields['countryIso'] = 'countryIso';
        $fields['countryText'] = 'countryText';
        $fields['stateText'] = 'stateText';
        $fields['abbreviationText'] = 'abbreviationText';
        $fields['addressLines'] = 'addressLines';

        return $fields;
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

        $rules[] = [['countryId', 'stateId'], 'integer', 'skipOnEmpty' => true, 'message' => Plugin::t('Country requires valid input.')];

        $rules[] = [
            ['stateId'], 'validateState', 'skipOnEmpty' => false, 'when' => function($model) {
                return (!$model->countryId || is_int($model->countryId)) && (!$model->stateId || is_int($model->stateId));
            }
        ];
        $rules[] = [['businessTaxId'], 'validateBusinessTaxId', 'skipOnEmpty' => true];

        $textAttributes =
            [
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
                'stateName',
                'stateValue',
                'custom1',
                'custom2',
                'custom3',
                'custom4',
                'notes',
                'label'
            ];

        // Trim all text attributes
        $rules[] = [$textAttributes, 'trim'];

        // Copy string attributes to new array to manipulate
        $textAttributesMinusMb4Allowed = $textAttributes;
        // Allow notes to contain emoji
        ArrayHelper::removeValue($textAttributesMinusMb4Allowed, 'notes');

        // Don't allow Mb4 for any strings
        $rules[] = [$textAttributesMinusMb4Allowed, StringValidator::class, 'disallowMb4' => true];

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
        $country = $this->getCountry();
        return $country ? $country->name : '';
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
     * @since 3.1.4
     */
    public function getCountryIso(): string
    {
        $country = $this->getCountry();
        return $country ? $country->iso : '';
    }

    /**
     * @return string
     */
    public function getStateText(): string
    {
        $state = $this->getState();
        if ($this->stateName) {
            if ($this->stateId && $state === null) {
                return '';
            }

            return $this->stateId ? $state->name : $this->stateName;
        }

        return $state ? $state->name : '';
    }

    /**
     * @return string
     */
    public function getAbbreviationText(): string
    {
        $state = $this->getState();
        return $state ? $state->abbreviation : '';
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
     * Return a keyed array of address lines. Useful for outputting an address in a consistent format
     *
     * @since 3.2.0
     */
    public function getAddressLines(): array
    {
        $addressLines = [
            'attention' => $this->attention,
            'name' => trim($this->title . ' ' . $this->firstName . ' ' . $this->lastName),
            'fullName' => $this->fullName,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'address3' => $this->address3,
            'city' => $this->city,
            'zipCode' => $this->zipCode,
            'phone' => $this->phone,
            'alternativePhone' => $this->alternativePhone,
            'label' => $this->label,
            'notes' => $this->notes,
            'businessName' => $this->businessName,
            'stateText' => $this->stateText,
            'custom1' => $this->custom1,
            'custom2' => $this->custom2,
            'custom3' => $this->custom3,
            'custom4' => $this->custom4,
        ];

        // Remove blank lines
        $addressLines = array_filter($addressLines);

        // Give plugins a chance to modify them
        $event = new DefineAddressLinesEvent([
            'addressLines' => $addressLines,
        ]);
        $this->trigger(self::EVENT_DEFINE_ADDRESS_LINES, $event);

        array_walk($event->addressLines, function(&$value, &$key) {
            $value = Craft::$app->getFormatter()->asText($value);
        });

        return $event->addressLines;
    }

    /**
     * @param Address|null $otherAddress
     */
    public function sameAs($otherAddress): bool
    {
        if (!$otherAddress || !$otherAddress instanceof self) {
            return false;
        }

        if (
            $this->attention == $otherAddress->attention &&
            $this->title == $otherAddress->title &&
            $this->firstName == $otherAddress->firstName &&
            $this->lastName == $otherAddress->lastName &&
            $this->fullName == $otherAddress->fullName &&
            $this->address1 == $otherAddress->address1 &&
            $this->address2 == $otherAddress->address2 &&
            $this->address3 == $otherAddress->address3 &&
            $this->city == $otherAddress->city &&
            $this->zipCode == $otherAddress->zipCode &&
            $this->phone == $otherAddress->phone &&
            $this->alternativePhone == $otherAddress->alternativePhone &&
            $this->label == $otherAddress->label &&
            $this->notes == $otherAddress->notes &&
            $this->businessName == $otherAddress->businessName &&
            (
                (!empty($this->getStateText()) && $this->getStateText() == $otherAddress->getStateText()) ||
                $this->stateValue == $otherAddress->stateValue
            ) &&
            (
                (!empty($this->getCountryText()) && $this->getCountryText() == $otherAddress->getCountryText()) ||
                $this->getCountryIso() == $otherAddress->getCountryIso()
            ) &&
            $this->custom1 == $otherAddress->custom1 &&
            $this->custom2 == $otherAddress->custom2 &&
            $this->custom3 == $otherAddress->custom3 &&
            $this->custom4 == $otherAddress->custom4
        ) {
            return true;
        }

        return false;
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
