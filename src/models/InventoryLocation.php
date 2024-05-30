<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Actionable;
use craft\base\Chippable;
use craft\base\CpEditable;
use craft\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\InventoryLocation as InventoryLocationRecord;
use craft\elements\Address;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;

/**
 * Inventory Location model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class InventoryLocation extends Model implements Chippable, CpEditable, Actionable
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
     * @inheritdoc
     */
    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($id);
    }

    /**
     * @inheritdoc
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
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/inventory-locations/' . $this->id);
    }

    /**
     * @inheritdoc
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

        $rules[] = [
            ['handle'],
            HandleValidator::class,
            'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title', 'create'],
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getId(): string|int|null
    {
        return $this->id;
    }

    /**
     * @inerhitdoc
     */
    public function getActionMenuItems(): array
    {
        return [
            [
                'label' => Craft::t('commerce', 'Edit'),
                'url' => $this->getCpEditUrl(),
                'icon' => 'edit',
            ],
        ];
    }
}
