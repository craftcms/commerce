<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Actionable;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Component\Contracts\CpEditable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\InventoryLocations;
use DateTime;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class InventoryLocation extends Component implements Chippable, CpEditable, Actionable
{
    public ?int $id = null;

    public string $name = '';

    public string $handle = '';

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    public ?int $addressId = null;

    private ?Address $_address = null;

    #[\Override]
    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return app(InventoryLocations::class)->getInventoryLocationById($id);
    }

    #[\Override]
    public function getUiLabel(): string
    {
        return t($this->name, category: 'site');
    }

    public function getAddress(): Address
    {
        if (!isset($this->_address)) {
            if ($id = $this->addressId) {
                /** @var Address $address */
                $address = Elements::getElementById($id);
                $this->_address = $address;
            } else {
                $this->_address = new Address();
                $this->_address->countryCode = 'US';
            }
        }

        $this->_address->title = $this->name;

        return $this->_address;
    }

    public function setAddress(Address $address): void
    {
        $this->setAddressId($address->id);
        $this->_address = $address;
    }

    public function getAddressLine(): string
    {
        if (!$this->addressId) {
            return '';
        }

        $address = $this->getAddress();
        return ($address->addressLine1 ?? '') . ' ' . $address->getCountryCode();
    }

    public function setAddressId(?int $id): void
    {
        $this->addressId = $id;
    }

    public function getAddressId(): ?int
    {
        return $this->addressId;
    }

    #[\Override]
    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/inventory-locations/' . $this->id);
    }

    public function getCpManageInventoryUrl(): string
    {
        return Url::cpUrl('commerce/inventory/levels/' . $this->handle);
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique(Table::INVENTORYLOCATIONS, 'name')->ignore($this->id),
            ],
            'handle' => [
                'required',
                'string',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique(Table::INVENTORYLOCATIONS, 'handle')->ignore($this->id),
                function($attribute, $value, $fail) {
                    $reserved = ['id', 'dateCreated', 'dateUpdated', 'uid', 'title', 'create'];
                    if (in_array($value, $reserved, true)) {
                        $fail(t('"{value}" is a reserved word.', ['value' => $value], category: 'commerce'));
                    }
                },
            ],
        ];
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getActionMenuItems(): array
    {
        $canManage = request()->craftUser()?->can('commerce-manageInventoryLocations') ?? false;
        if (!$canManage) {
            return [];
        }

        return [
            [
                'label' => t('Edit', category: 'commerce'),
                'url' => $this->getCpEditUrl(),
                'icon' => 'edit',
            ],
        ];
    }
}
