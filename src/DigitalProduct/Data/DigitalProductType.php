<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\FieldLayout\Contracts\FieldLayoutProviderInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Validation\DigitalProductTypeRules;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

use function CraftCms\Cms\t;

/**
 * A digital product type: its name, handle, automatic SKU format, per-site URL settings, and the
 * field layout of its digital products.
 */
#[Ruleset(DigitalProductTypeRules::class)]
class DigitalProductType extends Component implements FieldLayoutProviderInterface
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?string $skuFormat = null;

    public ?int $fieldLayoutId = null;

    public ?string $uid = null;

    /** @var DigitalProductTypeSite[]|null */
    private ?array $_siteSettings = null;

    private ?FieldLayout $_fieldLayout = null;

    public function __toString(): string
    {
        return (string)$this->handle;
    }

    public function getHandle(): ?string
    {
        return $this->handle;
    }

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/settings/digital-product-types/' . $this->id);
    }

    #[\Override]
    public function afterValidate(?Validator $validator = null): void
    {
        $fieldLayout = $this->getFieldLayout();
        $fieldLayout->reservedFieldHandles = [
            'existingLicenses',
            'licenses',
            'price',
            'product',
            'promotable',
            'sku',
            'type',
        ];

        if (!$fieldLayout->validate()) {
            $this->addModelErrors($fieldLayout, 'fieldLayout');
        }

        if (empty($this->getSiteSettings())) {
            $this->errors()->add('siteSettings', t('At least one site must be enabled for the digital product type.', category: 'commerce'));
        }
    }

    /**
     * @return int[]
     */
    public function getSiteIds(): array
    {
        return array_keys($this->getSiteSettings());
    }

    /**
     * @return DigitalProductTypeSite[] The type's site settings, indexed by site ID.
     */
    public function getSiteSettings(): array
    {
        if (isset($this->_siteSettings)) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(Arr::keyBy(app(DigitalProductTypes::class)->getDigitalProductTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    /**
     * @param DigitalProductTypeSite[] $siteSettings
     */
    public function setSiteSettings(array $siteSettings): void
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setDigitalProductType($this);
        }
    }

    public function getFieldLayout(): FieldLayout
    {
        if (isset($this->_fieldLayout)) {
            return $this->_fieldLayout;
        }

        if ($this->fieldLayoutId) {
            $fieldLayout = Fields::getLayoutById($this->fieldLayoutId, true)
                ?? throw new \RuntimeException('Invalid field layout ID: ' . $this->fieldLayoutId);
        } else {
            $fieldLayout = new FieldLayout(['type' => DigitalProduct::class]);
        }

        $fieldLayout->provider = $this;

        return $this->_fieldLayout = $fieldLayout;
    }

    public function setFieldLayout(FieldLayout $fieldLayout): void
    {
        $fieldLayout->provider = $this;
        $this->_fieldLayout = $fieldLayout;
    }

    /**
     * Returns the type's project config data.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'skuFormat' => $this->skuFormat,
            'siteSettings' => [],
        ];

        $fieldLayout = $this->getFieldLayout();

        if ($fieldLayoutConfig = $fieldLayout->getConfig()) {
            if (empty($fieldLayout->id)) {
                $fieldLayout->uid ??= (string)Str::uuid();
                $layoutUid = $fieldLayout->uid;
            } else {
                $layoutUid = DB::table(CraftTable::FIELDLAYOUTS)->uidById($fieldLayout->id);
            }

            $config['fieldLayouts'] = [$layoutUid => $fieldLayoutConfig];
        }

        foreach ($this->getSiteSettings() as $siteId => $settings) {
            $siteUid = DB::table(CraftTable::SITES)->uidById($siteId);
            $config['siteSettings'][$siteUid] = [
                'hasUrls' => $settings->hasUrls,
                'uriFormat' => $settings->uriFormat,
                'template' => $settings->template,
            ];
        }

        return $config;
    }
}
