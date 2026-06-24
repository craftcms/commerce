<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use craft\commerce\base\Purchasable;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use DateTime;
use Illuminate\Validation\Rule;

class InventoryItem extends Component
{
    public int $id;

    public int $purchasableId;

    public string $countryCodeOfOrigin;

    public string $administrativeAreaCodeOfOrigin;

    public string $harmonizedSystemCode;

    public ?string $uid = null;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?Purchasable $_purchasable = null;

    public function getPurchasable(null|string|int $siteId = null): ?Purchasable
    {
        if ($this->_purchasable !== null) {
            return $this->_purchasable;
        }

        /** @phpstan-ignore-next-line */
        $this->_purchasable = Elements::getElementById($this->purchasableId, siteId: $siteId);

        return $this->_purchasable;
    }

    public function getSku(): string
    {
        /** @phpstan-ignore-next-line */
        return $this->getPurchasable('*')->sku;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'purchasableId' => ['required', 'integer', Rule::unique(Table::INVENTORYITEMS, 'purchasableId')],
        ];
    }
}
