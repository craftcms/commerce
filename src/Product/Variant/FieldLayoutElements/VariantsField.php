<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Enums\ElementIndexViewMode;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Form\Contracts\Control;
use CraftCms\Cms\Form\Enums\ControlMode;
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

    #[Override]
    protected function formControl(FieldLayoutElementContext $context): ?Control
    {
        $product = $context->element;

        if (!$product instanceof Product) {
            throw new InvalidArgumentException('VariantsField can only be used in product field layouts.');
        }

        $static = $context->mode !== ControlMode::Editable
            || $context->form->mode !== ControlMode::Editable
            || $product->getIsRevision();

        return $product->getVariantManager()->formControl(
            $this->attribute(),
            $product,
            'index',
            $this->nestedElementManagerConfig($product, $static),
        );
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

        return $element->getVariantManager()->getIndexHtml(
            $element,
            $this->nestedElementManagerConfig($element, $static),
        );
    }

    /** @return array<string, mixed> */
    private function nestedElementManagerConfig(Product $product, bool $static): array
    {
        return [
            'canCreate' => !$static,
            'canPaste' => !$static,
            'minElements' => 0,
            'maxElements' => $product->getType()->maxVariants,
            'allowedViewModes' => [ElementIndexViewMode::Cards, ElementIndexViewMode::Table],
            'sortable' => !$static,
            'fieldLayouts' => [$product->getType()->getVariantFieldLayout()],
            'static' => $static,
        ];
    }
}
