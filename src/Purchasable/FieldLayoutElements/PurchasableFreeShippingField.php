<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableFreeShippingField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'freeShipping';

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $element = $context->element;
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return Lightswitch::make('freeShipping')
            ->size('small')
            ->value($element->freeShipping);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return FormFields::lightswitchFromConfig([
            'id' => 'free-shipping',
            'name' => 'freeShipping',
            'small' => true,
            'on' => $element->freeShipping,
            'disabled' => $static,
        ])->toHtml();
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Free Shipping', category: 'commerce');
    }
}
