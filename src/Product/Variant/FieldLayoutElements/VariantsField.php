<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Enums\ElementIndexViewMode;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Support\Facades\DeltaRegistry;
use CraftCms\Commerce\Product\Elements\Product;
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

    /**
     * Variants can't be managed from the Inertia product editor until it has a nested element manager control.
     */
    #[Override]
    public function formNode(FieldLayoutElementContext $context): ?Node
    {
        if (!$context->element instanceof Product) {
            throw new InvalidArgumentException('VariantsField can only be used in product field layouts.');
        }

        return Callout::make(
            $this->uid ?? $this->attribute,
            t('Variants can’t be edited from this screen yet.', category: 'commerce'),
        )->variant('warning');
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
