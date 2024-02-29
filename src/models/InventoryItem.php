<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\helpers\UrlHelper;

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
     * @var string
     */
    public string $uid;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateCreated = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateUpdated = null;

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/inventory/item/' . $this->id);
    }

    /**
     * @return ?Purchasable
     */
    public function getPurchasable(): ?Purchasable
    {
        /** @var ?Purchasable $purchasable */
        $purchasable = \Craft::$app->getElements()->getElementById($this->purchasableId);

        return $purchasable;
    }

    public function getSku(): string
    {
        return $this->getPurchasable()->sku;
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
