<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;

class ProductTypeSite extends Component
{
    public ?int $id = null;

    public int $productTypeId;

    public int $siteId;

    public bool $hasUrls = false;

    public ?string $uriFormat = null;

    public ?string $template = null;

    public bool $enabledByDefault = true;

    public bool $uriFormatIsRequired = true;

    private ?ProductType $_productType = null;

    private ?Site $_site = null;

    public function getProductType(): ProductType
    {
        if ($this->_productType !== null) {
            return $this->_productType;
        }

        if (!$this->productTypeId) {
            throw new \InvalidArgumentException('Product type site is missing its product type ID');
        }

        if (($this->_productType = app(ProductTypes::class)->getProductTypeById($this->productTypeId)) === null) {
            throw new \InvalidArgumentException('Invalid product type ID: ' . $this->productTypeId);
        }

        return $this->_productType;
    }

    public function setProductType(ProductType $productType): void
    {
        $this->_productType = $productType;
    }

    public function getSite(): Site
    {
        if ($this->_site !== null) {
            return $this->_site;
        }

        if (!$this->siteId) {
            throw new \InvalidArgumentException('Product type site is missing its site ID');
        }

        if (($this->_site = Sites::getSiteById($this->siteId)) === null) {
            throw new \InvalidArgumentException('Invalid site ID: ' . $this->siteId);
        }

        return $this->_site;
    }

    #[\Override]
    public function getRules(): array
    {
        if ($this->uriFormatIsRequired) {
            return ['uriFormat' => ['required']];
        }

        return [];
    }
}
