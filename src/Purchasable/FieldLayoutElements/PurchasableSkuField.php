<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableSkuField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public bool $required = true;

    #[Override]
    public string $attribute = 'sku';

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        // Hide the plain-text SKU display for draft variants whose product type generates the
        // SKU from a formula — legacy also excluded the field-layout-designer preview scenario
        // here (`$this->getScenario() === Element::SCENARIO_DEFAULT`), which has no new-system
        // equivalent; the draft check alone covers the behavior that matters at runtime.
        $variantWithSkuFormula = $element instanceof Variant && $element->getOwner()->getType()->skuFormat !== null;
        if ($variantWithSkuFormula && $element->getIsDraft()) {
            return null;
        }

        return PurchasableHelper::skuInputHtml($element->getSkuAsText(), [
            'disabled' => $static,
        ]);
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('SKU', category: 'commerce');
    }
}
