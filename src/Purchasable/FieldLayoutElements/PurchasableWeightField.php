<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use craft\commerce\Plugin;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableWeightField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'weight';

    #[Override]
    protected function showLabel(): bool
    {
        return true;
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

        return FormFields::textHtml([
            'id' => 'weight',
            'name' => 'weight',
            'value' => $element->weight !== null ? I18N::getFormatter()->asDecimal($element->weight) : '',
            'class' => 'text',
            'size' => 10,
            // TODO: migrate to app(Plugin::class)->getSettings()->weightUnits once Settings service migrated to src/
            'unit' => Plugin::getInstance()->getSettings()->weightUnits,
            'placeholder' => t('Weight', category: 'commerce'),
            'disabled' => $static,
        ]);
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Weight', category: 'commerce');
    }
}
