<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasablePromotableField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'promotable';

    /**
     * Whether the field should be checked by default when creating a new purchasable.
     */
    public bool $defaultPromotable = false;

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

        return FormFields::lightswitchFromConfig([
            'id' => 'promotable',
            'name' => 'promotable',
            'small' => true,
            'on' => $element->getIsFresh() ? $this->defaultPromotable : $element->promotable,
            'disabled' => $static,
        ])->toHtml();
    }

    #[Override]
    protected function settingsHtml(): ?string
    {
        return parent::settingsHtml() . FormFields::lightswitchFromConfig([
            'id' => 'defaultPromotable',
            'name' => 'defaultPromotable',
            'label' => t('Default Value'),
            'on' => $this->defaultPromotable,
        ])->toHtml();
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Promotable', category: 'commerce');
    }
}
