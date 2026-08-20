<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use craft\commerce\Plugin;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableDimensionsField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'dimensions';

    #[Override]
    protected function showLabel(): bool
    {
        return false;
    }

    #[Override]
    public function showInForm(?ElementInterface $element = null): bool
    {
        if ($element instanceof Variant && !$element->getOwner()->getType()->hasDimensions) {
            return false;
        }

        return parent::showInForm($element);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        // TODO: migrate to app(Plugin::class)->getSettings()->dimensionUnits once Settings service migrated to src/
        $dimensionUnits = Plugin::getInstance()->getSettings()->dimensionUnits;

        return Html::beginTag('div', ['class' => 'flex']) .
            FormFields::fieldHtml(FormFields::textHtml([
                'id' => 'length',
                'name' => 'length',
                'value' => $element->length !== null ? I18N::getFormatter()->asDecimal($element->length) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => $dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'length', 'label' => t('Length', category: 'commerce')]) .
            FormFields::fieldHtml(FormFields::textHtml([
                'id' => 'width',
                'name' => 'width',
                'value' => $element->width !== null ? I18N::getFormatter()->asDecimal($element->width) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => $dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'width', 'label' => t('Width', category: 'commerce')]) .
            FormFields::fieldHtml(FormFields::textHtml([
                'id' => 'height',
                'name' => 'height',
                'value' => $element->height !== null ? I18N::getFormatter()->asDecimal($element->height) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => $dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'height', 'label' => t('Height', category: 'commerce')]) .
        Html::endTag('div');
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Dimensions', category: 'commerce');
    }
}
