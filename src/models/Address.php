<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use CommerceGuys\Addressing\AddressFormat\AddressFormat;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\AddressInterface;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Formatter\PostalLabelFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Craft;
use craft\commerce\base\Model;
use craft\commerce\events\DefineAddressLinesEvent;
use craft\commerce\events\ModifyFormAttributesEvent;
use craft\commerce\events\ShowAllFormAttributesEvent;
use craft\commerce\helpers\Address as AddressHelper;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\validators\StringValidator;
use DateTime;
use DvK\Vat\Validator;
use Exception;
use LitEmoji\LitEmoji;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

/**
 * Address Model
 *
 * @property Country $country
 * @property string $countryText
 * @property string $cpEditUrl
 * @property State $state
 * @property string $stateText
 * @property string $abbreviationText
 * @property int|string $administrativeValue
 * @property string $countryIso
 * @property Validator $vatValidator
 * @property-read array $addressLines
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Address extends Model implements AddressInterface
{
    /**
     * @event DefineAddressLinesEvent The event that is triggered when determining the lines of the address to display.
     * @see getAddressLines()
     * @since 3.2.0
     */
    const EVENT_DEFINE_ADDRESS_LINES = 'defineAddressLines';
    
    /** @since 4.0 */
    const DEFAULT_COUNTRY_ISO = 'US';

    /**
     * @event ModifyFormAttributesEvent
     * @see formAttributes()
     * @since 4.0
     */
    const EVENT_MODIFY_FORM_ATTRIBUTES = 'modifyFormAttributes';
    
    
    const EVENT_SHOW_ALL_FORM_ATTRIBUTES = 'showFormAttributes';

    /**
     * @var int|null Address ID
     */
    public ?int $id = null;

    /**
     * @var bool Is this the store location.
     */
    public bool $isStoreLocation = false;

    /**
     * @var string|null Attention
     */
    public ?string $attention = null;

    /**
     * @var string|null Title
     */
    public ?string $title = null;


    /**
     * @var string|null First Name
     */
    public ?string $giveName = null;
    
    /**
     * @var string|null Family Name
     */
    public ?string $familyName = null;

    /**
     * @var string|null Full Name
     * @since 2.2
     */
    public ?string $fullName = null;

    /**
     * @var string|null Address Line 1
     */
    public ?string $addressLine1 = null;
    
    /**
     * @var string|null Address Line 2
     */
    public ?string $address2 = null;


    /**
     * @var string|null Address Line 3
     * @since 2.2
     */
    public ?string $address3 = null;


    /**
     * @var string|null City
     */
    public ?string $city = null;


    /**
     * @var string|null Zip
     */
    public ?string $zipCode = null;


    /**
     * @var string|null Phone
     */
    public ?string $phone = null;

    /**
     * @var string|null Alternative Phone
     */
    public ?string $alternativePhone = null;

    /**
     * @var string|null Label
     * @since 2.2
     */
    public ?string $label = null;
    
    /**
     * @var string|null Business Name
     */
    public ?string $businessName = null;
    
    /**
     * @var string|null Business Tax ID
     */
    public ?string $businessTaxId = null;

    /**
     * @var string|null Business ID
     */
    public ?string $businessId = null;

    /**
     * @var string|null State Name
     */
    public ?string $stateName = null;

    /**
     * @var int|null Country ID
     */
    public ?int $countryId = null;

    /**
     * @var int|null State ID
     */
    public ?int $stateId = null;

    /**
     * @var string|null Notes, only field that can contain Emoji
     * @since 2.2
     */
    public ?string $notes = null;
    
    /**
     * @var string|null Custom Field 1
     * @since 2.2
     */
    public ?string $custom1 = null;
    
    /**
     * @var string|null Custom Field 2
     * @since 2.2
     */
    public ?string $custom2 = null;

    /**
     * @var string|null Custom Field 3
     * @since 2.2
     */
    public ?string $custom3 = null;

    /**
     * @var string|null Custom Field 4
     * @since 2.2
     */
    public ?string $custom4 = null;

    /**
     * @var bool If this address is used for estimated values
     * @since 2.2
     */
    public bool $isEstimated = false;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var int|string|null Can be a State ID or State Name
     */
    private $_administrativeValue = null;

    /**
     * @var
     */
    private $_vatValidator;

    /**
     * @var string Country Code
     */
    public $countryCode;

    /**
     * @var string Administrative area id
     */
    public $administrativeAreaId;
    
    /**
     * @var string Administrative name
     */
    public $administrativeAreaName;

    /**
     * @var string Locality (City)
     */
    public $locality;

    /**
     * @var string Dependent Locality
     */
    public $dependentLocality;

    /**
     * @var string Postal code
     */
    public $postalCode;

    /**
     * @var string Sorting code
     */
    public $sortingCode;

    /**
     * @var string Address line 2
     */
    public $addressLine2;    
    
    /**
     * @var string Address line3
     */
    public $addressLine3;

    /**
     * @var string Organization
     */
    public $organization;

    /**
     * @var string Given name (First name)
     */
    public $givenName;

    /**
     * @var string Additional name (Middle name / Patronymic)
     */
    public $additionalName;
    
    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->notes = LitEmoji::shortcodeToUnicode($this->notes);
        // $this->isEstimated = (bool)$this->isEstimated;
        // $this->isStoreLocation = (bool)$this->isStoreLocation;

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
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'administrativeValue';

        return $names;
    }

    /**
     * @inheritDoc
     * @since 3.2.1
     */
    public function fields(): array
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
    public function extraFields(): array
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
        $labels['givenName'] = Craft::t('commerce', 'First Name');
        $labels['familyName'] = Craft::t('commerce', 'Last Name');
        $labels['fullName'] = Craft::t('commerce', 'Full Name');
        $labels['attention'] = Craft::t('commerce', 'Attention');
        $labels['title'] = Craft::t('commerce', 'Title');
        $labels['addressLine1'] = Craft::t('commerce', 'Address 1');
        $labels['address2'] = Craft::t('commerce', 'Address 2');
        $labels['address3'] = Craft::t('commerce', 'Address 3');
        $labels['city'] = Craft::t('commerce', 'City');
        $labels['zipCode'] = Craft::t('commerce', 'Zip Code');
        $labels['phone'] = Craft::t('commerce', 'Phone');
        $labels['alternativePhone'] = Craft::t('commerce', 'Alternative Phone');
        $labels['organization'] = Craft::t('commerce', 'Business Name');
        $labels['businessId'] = Craft::t('commerce', 'Business ID');
        $labels['businessTaxId'] = Craft::t('commerce', 'Business Tax ID');
        $labels['countryId'] = Craft::t('commerce', 'Country');
        $labels['stateId'] = Craft::t('commerce', 'State');
        $labels['stateName'] = Craft::t('commerce', 'State');
        $labels['administrativeValue'] = Craft::t('commerce', 'State');
        $labels['custom1'] = Craft::t('commerce', 'Custom 1');
        $labels['custom2'] = Craft::t('commerce', 'Custom 2');
        $labels['custom3'] = Craft::t('commerce', 'Custom 3');
        $labels['custom4'] = Craft::t('commerce', 'Custom 4');
        $labels['notes'] = Craft::t('commerce', 'Notes');
        $labels['label'] = Craft::t('commerce', 'Label');
        return $labels;
    }

    /**
     * @inheritDoc
     */
    protected function defineRules(): array
    {
        $rules = [
            [['countryId', 'stateId'], 'integer', 'skipOnEmpty' => true, 'message' => Craft::t('commerce', 'Country requires valid input.')],
            [
                ['stateId'],
                'validateState',
                'skipOnEmpty' => false,
                'when' => function($model) {
                    return (!$model->countryId || is_numeric($model->countryId)) && (!$model->stateId || is_numeric($model->stateId));
                },
            ],
            [['businessTaxId'], 'validateBusinessTaxId', 'skipOnEmpty' => true],
        ];

        $textAttributes = [
            'givenName',
            'familyName',
            'fullName',
            'attention',
            'title',
            'addressLine1',
            'address2',
            'address3',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessId',
            'organization',
            'stateName',
            'administrativeValue',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'notes',
            'label',
        ];

        // Trim all text attributes
        $rules[] = [$textAttributes, 'trim'];

        // Copy string attributes to new array to manipulate
        $textAttributesMinusMb4Allowed = $textAttributes;
        // Allow notes to contain emoji
        ArrayHelper::removeValue($textAttributesMinusMb4Allowed, 'notes');

        // Don't allow Mb4 for any strings
        $rules[] = [$textAttributesMinusMb4Allowed, StringValidator::class, 'disallowMb4' => true];

        // Set safe attributes to allow assignment via set attributes
        $rules[] = [['id'], 'safe'];

        return $rules;
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     */
    public function validateState($attribute, $params, $validator): void
    {
        $country = $this->countryId ? Plugin::getInstance()->getCountries()->getCountryById($this->countryId) : null;
        $state = $this->stateId ? Plugin::getInstance()->getStates()->getAdministrativeAreaById($this->stateId) : null;
        if ($country && $country->isStateRequired && (!$state || ($state && $state->countryId !== $country->id))) {
            $this->addError('administrativeValue', Craft::t('commerce', 'Country requires a related state selected.'));
        }
    }

    /**
     * @param $attribute
     * @param $params
     * @param $validator
     */
    public function validateBusinessTaxId($attribute, $params, $validator): void
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
     * @return string
     */
    public function getCountryName(): string
    {
        $country = $this->getCountry();
        return $country->name ?? '';
    }

    /**
     * @return string
     * @deprecated in 4.0. Use [[getCountryName]] instead.
     */
    public function getCountryText(): string
    {
        return $this->getCountryName();
    }

    /**
     * @return Country|null
     */
    public function getCountry(): ?Country
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
        return $country->iso ?? '';
    }

    /**
     * @return string
     */
    public function getStateName(): string
    {
        $state = $this->getState();
        if ($this->stateName) {
            if ($this->stateId && $state === null) {
                return '';
            }

            return $this->stateId ? $state->name : $this->stateName;
        }

        return $state->name ?? '';
    }

    /**
     * @return string
     * @deprecated in 4.0. Use [[getStateName]] instead.
     */
    public function getStateText(): string
    {
        return $this->getStateName();
    }

    /**
     * @return string
     */
    public function getStateAbbreviation(): string
    {
        $state = $this->getState();
        return $state->abbreviation ?? '';
    }

    /**
     * @return string
     * @deprecated in 4.0. Use [[getStateAbbreviation]] instead.
     */
    public function getAbbreviationText(): string
    {
        return $this->getStateAbbreviation();
    }

    /**
     * @return State|null
     */
    public function getState(): ?State
    {
        return $this->stateId ? Plugin::getInstance()->getStates()->getAdministrativeAreaById($this->stateId) : null;
    }

    /**
     * @return int|string
     */
    public function getadministrativeValue()
    {
        if ($this->_administrativeValue === null) {
            if ($this->stateName) {
                return $this->stateId ?: $this->stateName;
            }

            return $this->stateId ?: '';
        }

        return $this->_administrativeValue;
    }

    /**
     * Sets the stateId or stateName based on the value parameter.
     *
     * @param string|int|null $value A state ID or a state name, null to clear the state from the address.
     * @throws InvalidConfigException
     */
    public function setAdministrativeValue($value): void
    {
        if ($value) {
            if (Plugin::getInstance()->getStates()->getAdministrativeAreaById((int)$value)) {
                $this->stateId = $value;
            } else {
                $this->stateId = null;
                $this->stateName = $value;
            }

            $this->_administrativeValue = $value;
        } else {
            $this->stateId = null;
            $this->stateName = null;
            $this->_administrativeValue = null;
        }
    }

    /**
     * Return a keyed array of address lines. Useful for outputting an address in a consistent format.
     *
     * @param bool $sanitize
     * @return array
     * @since 3.2.0
     * @deprecated in 4.0 use [[getAddressHtml]]
     */
    public function getAddressLines(bool $sanitize = false): array
    {
        $addressLines = [
            'attention' => $this->attention,
            'name' => trim($this->title . ' ' . $this->giveName . ' ' . $this->familyName),
            'fullName' => $this->fullName,
            'addressLine1' => $this->addressLine1,
            'address2' => $this->address2,
            'address3' => $this->address3,
            'city' => $this->city,
            'zipCode' => $this->zipCode,
            'phone' => $this->phone,
            'alternativePhone' => $this->alternativePhone,
            'label' => $this->label,
            'notes' => $this->notes,
            'organization' => $this->organization,
            'businessTaxId' => $this->businessTaxId,
            'stateText' => $this->stateText,
            'countryText' => $this->countryText,
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

        if ($sanitize) {
            array_walk($event->addressLines, static function(&$value) {
                $value = Craft::$app->getFormatter()->asText($value);
            });
        }

        return $event->addressLines;
    }

    /**
     * Return a address formatted html based on the country's ISO
     * 
     * @return string
     * @sinice 4.0
     */
    public function getAddressHtml($formatter = 'default'): string
    {
        // based display in formAttributes
        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository = new CountryRepository();
        $subdivisionRepository = new SubdivisionRepository();
        
        switch ($formatter) {
            case 'postal':
                $formatter = new PostalLabelFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository, [
                    'origin_country' => 'US'
                ]);
                break;
                
            default:
                $formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository);
        }

        return $formatter->format($this);
    }

    /**
     * @return string
     */
    public function getAddressFormat(): AddressFormat
    {
        $addressFormatRepository = new AddressFormatRepository();
        
        return $addressFormatRepository->get($this->countryIso);
    }

    /**
     * This method can be used to determine if the other addresses supplied has the same address contents (minus the address ID).
     *
     * @param Address|null $otherAddress
     * @return bool
     * @since 3.2.1
     */
    public function sameAs(?Address $otherAddress): bool
    {
        if (!$otherAddress || !$otherAddress instanceof self) {
            return false;
        }

        if (
            $this->attention == $otherAddress->attention &&
            $this->title == $otherAddress->title &&
            $this->giveName == $otherAddress->giveName &&
            $this->familyName == $otherAddress->familyName &&
            $this->fullName == $otherAddress->fullName &&
            $this->addressLine1 == $otherAddress->addressLine1 &&
            $this->address2 == $otherAddress->address2 &&
            $this->address3 == $otherAddress->address3 &&
            $this->city == $otherAddress->city &&
            $this->zipCode == $otherAddress->zipCode &&
            $this->phone == $otherAddress->phone &&
            $this->alternativePhone == $otherAddress->alternativePhone &&
            $this->label == $otherAddress->label &&
            $this->notes == $otherAddress->notes &&
            $this->organization == $otherAddress->organization &&
            (
                (!empty($this->getStateName()) && $this->getStateName() == $otherAddress->getStateName()) ||
                $this->administrativeValue == $otherAddress->administrativeValue
            ) &&
            (
                (!empty($this->getCountryName()) && $this->getCountryName() == $otherAddress->getCountryName()) ||
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
    private function _validateVatNumber(string $businessVatId): bool
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
        if (null === $this->_vatValidator) {

            $this->_vatValidator = new Validator();
        }

        return $this->_vatValidator;
    }

    public function getCountryCode()
    {
        return $this->getCountryIso();
    }

    public function getAdministrativeArea()
    {
        return $this->getStateText();
    }

    public function getLocality()
    {
        return $this->city;
    }

    public function getDependentLocality()
    {
        return $this->dependentLocality;
    }

    public function getPostalCode()
    {
        return $this->zipCode ?? $this->postalCode;
    }

    public function getSortingCode()
    {
        return $this->sortingCode;
    }

    public function getAddressLine1()
    {
        return $this->addressLine1;
    }

    public function getAddressLine2()
    {
        return $this->address2;
    }

    public function getOrganization()
    {
        return $this->organization;
    }

    public function getGivenName()
    {
        return $this->giveName;
    }

    public function getAdditionalName()
    {
        return $this->additionalName;
    }

    public function getFamilyName()
    {
        return $this->familyName;
    }

    public function getLocale()
    {
        $countryRepository = new CountryRepository();
        return $countryRepository->get($this->countryIso)->getLocale();
    }

    public function formAttributes(): array
    {
        $showEvent = new ShowAllFormAttributesEvent();
        
        if ($this->hasEventHandlers(self::EVENT_SHOW_ALL_FORM_ATTRIBUTES)) {
            $this->trigger(self::EVENT_SHOW_ALL_FORM_ATTRIBUTES, $showEvent);
        }
        
        $attributes = $this->attributes();
       
        if ($showEvent->all === false) {
            if ($this->countryIso === null) {
                $this->countryIso = AddressHelper::getDefaultCountry()->iso;
            }
            
            $format = $this->getAddressFormat();

            $attributes = array_merge($format->getUsedFields(), $this->getImportantAttributes());
        }

        $modifyEvent = new ModifyFormAttributesEvent([
            'attributes' => $attributes
        ]);
        
        $this->trigger(self::EVENT_MODIFY_FORM_ATTRIBUTES, $modifyEvent);

        return $modifyEvent->attributes;
    }
    
    public function getImportantAttributes(): array
    {
        return [
          'phone'  
        ];
    }
    
    public function getAddress1()
    {
        return $this->addressLine1;
    }
}
