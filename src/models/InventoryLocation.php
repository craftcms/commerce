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
use DateTime;

/**
 * Inventory Location model
 *
 * @since 5.0
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
    public DateTime|null $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public DateTime|null $dateUpdated = null;

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
        /** @phpstan-ignore-next-line */
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
                /** @var Address $address */
                $address = Craft::$app->getElements()->getElementById($id);
                $this->_address = $address;
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
     */
    public function setAddress(Address $address): void
    {
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

        $rules[] = [['name', 'handle'], 'required'];
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
