<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Field\Enums\TranslationMethod;
use CraftCms\Cms\FieldLayout\LayoutElements\TitleField;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Catalog\Elements\Variant;
use InvalidArgumentException;
use Override;

/**
 * VariantTitleField represents a Title field that can be included within a product type's variant field layout designer.
 */
class VariantTitleField extends TitleField
{
    #[Override]
    protected function selectorInnerHtml(): string
    {
        return
            Html::tag('span', '', [
                'class' => ['fld-variant-title-field-icon', 'fld-field-hidden', 'hidden'],
            ]) .
            parent::selectorInnerHtml();
    }

    #[Override]
    protected function translatable(?ElementInterface $element = null, bool $static = false): bool
    {
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException(sprintf('%s can only be used in variant field layouts.', self::class));
        }

        return $element->getOwner()->getType()->variantTitleTranslationMethod !== TranslationMethod::None->value;
    }

    #[Override]
    protected function translationDescription(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException(sprintf('%s can only be used in variant field layouts.', self::class));
        }

        /** @phpstan-ignore-next-line nullsafe.neverNull (variantTitleTranslationMethod is an uncast, free-form DB string column - tryFrom() genuinely can return null) */
        return TranslationMethod::tryFrom($element->getOwner()->getType()->variantTitleTranslationMethod)?->description();
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException('VariantTitleField can only be used in variant field layouts.');
        }

        if (!$element->getOwner()->getType()->hasVariantTitleField) {
            return null;
        }

        return parent::inputHtml($element, $static);
    }
}
