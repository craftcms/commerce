<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;

/**
 * Inventory Item model
 * @since 5.0
 */
class InventoryItem extends Model
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int
     */
    public int $purchasableId;

    /**
     * @var string
     */
    public string $countryCodeOfOrigin;

    /**
     * @var string
     */
    public string $administrativeAreaCodeOfOrigin;

    /**
     * @var string
     */
    public string $harmonizedSystemCode;

    /**
     * @var ?string
     */
    public ?string $uid = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateCreated = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateUpdated = null;

    /**
     * @var Purchasable|null
     */
    private ?Purchasable $_purchasable = null;
    
    /**
     * @return ?Purchasable
     */
    public function getPurchasable(null|string|int $siteId = null): ?Purchasable
    {
        if ($this->_purchasable !== null) {
            return $this->_purchasable;
        }

        /** @phpstan-ignore-next-line */
        $this->_purchasable = Craft::$app->getElements()->getElementById(elementId: $this->purchasableId, siteId: $siteId);

        /** @phpstan-ignore-next-line */
        return $this->_purchasable;
    }

    public function getSku(): string
    {
        return $this->getPurchasable('*')->sku;
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // unique based on purchasableId
            [['purchasableId'], 'unique', 'targetClass' => InventoryItem::class, 'targetAttribute' => ['purchasableId']],
            [['sku'], 'unique', 'targetClass' => InventoryItem::class, 'targetAttribute' => ['sku']],
        ]);
    }
}
