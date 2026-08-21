<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Enums\ElementIndexViewMode;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\DeltaRegistry;
use CraftCms\Commerce\Catalog\Elements\Product;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

/**
 * VariantsField represents a Variants field that can be included within a product type's product field layout designer.
 */
class VariantsField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'variants';

    #[Override]
    public function hasCustomWidth(): bool
    {
        return false;
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Variants', category: 'commerce');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException('VariantsField can only be used in product field layouts.');
        }

        DeltaRegistry::registerName($this->attribute());

        $maxVariants = $element->getType()->maxVariants;

        return $element->getVariantManager()->getIndexHtml($element, [
            'canCreate' => !$static,
            'canPaste' => !$static,
            'minElements' => 0,
            'maxElements' => $maxVariants ?? null,
            'allowedViewModes' => [ElementIndexViewMode::Cards, ElementIndexViewMode::Table],
            'sortable' => !$static,
            'fieldLayouts' => [$element->getType()->getVariantFieldLayout()],
        ]);
    }
}
