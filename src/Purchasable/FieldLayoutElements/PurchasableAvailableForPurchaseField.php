<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableAvailableForPurchaseField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'availableForPurchase';

    /**
     * Whether the field should be checked by default when creating a new purchasable.
     */
    public bool $defaultAvailableForPurchase = false;

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

        return Lightswitch::make('availableForPurchase')
            ->size('small')
            ->value($element->getIsFresh() ? $this->defaultAvailableForPurchase : $element->availableForPurchase);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return PurchasableHelper::availableForPurchaseInputHtml($element->getIsFresh() ? $this->defaultAvailableForPurchase : $element->availableForPurchase, [
            'disabled' => $static,
        ]);
    }

    #[Override]
    protected function settingsNodes(UiContext $context): array
    {
        return [
            ...parent::settingsNodes($context),
            Field::make(t('Default Value'), Lightswitch::make('defaultAvailableForPurchase')
                ->value($this->defaultAvailableForPurchase)),
        ];
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Available for purchase', category: 'commerce');
    }
}
