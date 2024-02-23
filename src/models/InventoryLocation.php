<?php

namespace craft\commerce\models;

use Craft;
use craft\base\Chippable;
use craft\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\InventoryLocation as InventoryLocationRecord;
use craft\elements\Address;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

/**
 * Location model
 */
class InventoryLocation extends Model implements Chippable
{
    /**
     * @var ?int
     */
    public ?int $id = null;

    /**
     * @var string
     */
    public string $name = '';

    /**
     * @var string
     */
    public string $handle = '';

    /**
     * @var DateTime|null
     */
    public ?\DateTime $dateCreated = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateUpdated = null;

    /**
     * @var ?int
     */
    public ?int $addressId = null;

    /**
     * @var ?Address
     */
    private ?Address $_address = null;

    /**
     * @inheritDoc
     */
    public static function get(int|string $id): ?static
    {
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($id);
    }

    /*
     * @inheritDoc
     */
    public function getUiLabel(): string
    {
        return $this->name;
    }

    /**
     * @return Address
     */
    public function getAddress(): Address
    {
        if (!isset($this->_address)) {
            if ($id = $this->addressId) {
                $this->_address = Craft::$app->getElements()->getElementById($id);
            } else {
                $this->_address = new Address();
                $this->_address->countryCode = 'US';
            }
        }

        return $this->_address;
    }

    /**
     * @param Address $address
     * @return void
     * @throws InvalidConfigException
     */
    public function setAddress(Address $address)
    {
        if (!$address->id) {
            throw new InvalidConfigException('Address must be saved before it can be set on an inventory location.');
        }
        $this->setAddressId($address->id);
        $this->_address = $address;
    }

    /**
     * @return string
     */
    public function getAddressLine(): string
    {
        return $this->addressId ? ($this->getAddress()->addressLine1 . ' ' . $this->getAddress()->getCountryCode()) : '';
    }

    /**
     * @param $id
     * @return void
     */
    public function setAddressId($id)
    {
        $this->addressId = $id;
    }

    /**
     * @return int|null
     */
    public function getAddressId()
    {
        return $this->addressId;
    }

    /**
     * @return string
     */
    public function cpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/inventory/locations/' . $this->id);
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['name'],
            UniqueValidator::class,
            'targetClass' => InventoryLocationRecord::class,
            'targetAttribute' => 'name',
            'message' => Craft::t('yii', '{attribute} "{value}" has already been taken.'),
        ];

        $rules[] = [
            ['handle'],
            UniqueValidator::class,
            'targetClass' => InventoryLocationRecord::class,
            'targetAttribute' => 'handle',
            'message' => Craft::t('yii', '{attribute} "{value}" has already been taken.'),
        ];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string|int|null
    {
        return $this->id;
    }
}
