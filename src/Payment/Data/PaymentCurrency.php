<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Store\Stores;
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

        $store = app(Stores::class)->getStoreById($this->storeId);
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
        return app(Currencies::class)->numericCodeFor($this->iso);
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
        return app(Currencies::class)->getSubunitFor($this->iso);
    }

    public function getName(): ?string
    {
        return $this->iso;
    }

    public function getStore(): \CraftCms\Commerce\Store\Data\Store
    {
        $store = app(Stores::class)->getStoreById($this->storeId);
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
