<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Field\Enums\TranslationMethod;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Product\Elements\Product;
use InvalidArgumentException;
use Override;

/**
 * ProductTitleField represents a Title field that can be included within a product type's product field layout designer.
 */
class ProductTitleField extends TitleField
{
    #[Override]
    protected function selectorInnerHtml(): string
    {
        return
            Html::tag('span', '', [
                'class' => ['fld-product-title-field-icon', 'fld-field-hidden', 'hidden'],
            ]) .
            parent::selectorInnerHtml();
    }

    #[Override]
    protected function translatable(?ElementInterface $element = null, bool $static = false): bool
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException(sprintf('%s can only be used in product field layouts.', self::class));
        }

        return $element->getType()->productTitleTranslationMethod !== TranslationMethod::None->value;
    }

    #[Override]
    protected function translationDescription(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException(sprintf('%s can only be used in product field layouts.', self::class));
        }

        /** @phpstan-ignore-next-line nullsafe.neverNull (productTitleTranslationMethod is an uncast, free-form DB string column - tryFrom() genuinely can return null) */
        return TranslationMethod::tryFrom($element->getType()->productTitleTranslationMethod)?->description();
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException('ProductTitleField can only be used in product field layouts.');
        }

        if (!$element->getType()->hasProductTitleField) {
            return null;
        }

        return parent::inputHtml($element, $static);
    }
}
