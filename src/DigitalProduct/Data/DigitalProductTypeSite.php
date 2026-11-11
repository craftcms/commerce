<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;

/**
 * A digital product type's settings for one site.
 */
class DigitalProductTypeSite extends Component
{
    public ?int $id = null;

    public ?int $digitalProductTypeId = null;

    public int $siteId;

    public bool $hasUrls = false;

    public ?string $uriFormat = null;

    public ?string $template = null;

    private ?DigitalProductType $_digitalProductType = null;

    public function getDigitalProductType(): DigitalProductType
    {
        if ($this->_digitalProductType !== null) {
            return $this->_digitalProductType;
        }

        if (!$this->digitalProductTypeId) {
            throw new \InvalidArgumentException('Digital product type site is missing its type ID');
        }

        return $this->_digitalProductType = app(DigitalProductTypes::class)->getDigitalProductTypeById($this->digitalProductTypeId)
            ?? throw new \InvalidArgumentException('Invalid digital product type ID: ' . $this->digitalProductTypeId);
    }

    public function setDigitalProductType(DigitalProductType $digitalProductType): void
    {
        $this->_digitalProductType = $digitalProductType;
    }

    public function getSite(): Site
    {
        return Sites::getSiteById($this->siteId)
            ?? throw new \InvalidArgumentException('Invalid site ID: ' . $this->siteId);
    }

    #[\Override]
    public function getRules(): array
    {
        return $this->hasUrls ? ['uriFormat' => ['required']] : [];
    }
}
