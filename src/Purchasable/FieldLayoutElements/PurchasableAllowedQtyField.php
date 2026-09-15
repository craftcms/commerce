<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableAllowedQtyField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'allowedQty';

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return Html::beginTag('div', ['class' => 'flex']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                FormFields::textHtml([
                    'id' => 'minQty',
                    'name' => 'minQty',
                    'value' => $element->minQty,
                    'placeholder' => t('Any', category: 'commerce'),
                    'title' => t('Minimum allowed quantity', category: 'commerce'),
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
            Html::tag('div', t('to', category: 'commerce'), ['class' => 'label light']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                FormFields::textHtml([
                    'id' => 'maxQty',
                    'name' => 'maxQty',
                    'value' => $element->maxQty,
                    'placeholder' => t('Any', category: 'commerce'),
                    'title' => t('Maximum allowed quantity', category: 'commerce'),
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
        Html::endTag('div');
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Allowed Qty', category: 'commerce');
    }
}
