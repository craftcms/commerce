<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Url;
use DateTime;
use Money\Currency;

class PaymentCurrency extends Component
{
    public ?int $id = null;

    public ?int $storeId = null;

    public ?string $iso = null;

    public float $rate = 1;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    public function __toString(): string
    {
        return (string)$this->iso;
    }

    public function getCurrency(): Currency
    {
        return new Currency($this->iso);
    }

    public function getCpEditUrl(): string
    {
        if ($this->storeId === null) {
            return '';
        }

        $store = Plugin::getInstance()->getStores()->getStoreById($this->storeId);
        if ($store === null) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        return Url::cpUrl(sprintf('commerce/store-management/%s/payment-currencies/%s', $store->handle, $this->id));
    }

    public function getAlphabeticCode(): ?string
    {
        return $this->iso;
    }

    public function getNumericCode(): ?int
    {
        return Plugin::getInstance()->getCurrencies()->numericCodeFor($this->iso);
    }

    public function getEntity(): ?string
    {
        return '';
    }

    #[\Deprecated(message: 'Use getSubUnit() instead.')]
    public function getMinorUnit(): ?int
    {
        return $this->getSubUnit();
    }

    public function getSubUnit(): ?int
    {
        return Plugin::getInstance()->getCurrencies()->getSubunitFor($this->iso);
    }

    public function getName(): ?string
    {
        return $this->iso;
    }

    public function getStore(): \craft\commerce\models\Store
    {
        $store = Plugin::getInstance()->getStores()->getStoreById($this->storeId);
        if ($store === null) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }

    public function getPrimary(): bool
    {
        return $this->getCode() === $this->getStore()->getCurrency()->getCode();
    }

    public function getCode(): ?string
    {
        return $this->iso;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'iso' => ['required', 'string'],
            'rate' => ['required', 'numeric'],
        ];
    }
}
