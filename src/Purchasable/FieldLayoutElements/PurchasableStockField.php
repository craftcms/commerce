<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableStockField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'stock';

    /**
     * Whether inventory should be tracked by default when creating a new purchasable.
     */
    public bool $defaultInventoryTracked = false;

    /**
     * Whether out of stock purchases should be allowed by default when creating a new purchasable.
     */
    public bool $defaultAllowOutOfStockPurchases = false;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    #[Override]
    public function uiNode(FieldLayoutElementContext $context): ?Node
    {
        $element = $context->element;
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        if (!$this->uid) {
            throw new InvalidArgumentException('Persisted Purchasable Stock FieldLayout elements require stable UIDs.');
        }

        if ($element->getIsRevision()) {
            /** @var Purchasable $element */
            $element = $element->getCanonical();
        }

        $static = $context->mode !== ControlMode::Editable;
        $status = $this->showStatus() ? $this->statusClass($element, $static) : null;
        $statusLabel = $status !== null
            ? ($this->statusLabel($element, $static) ?? ucfirst($status))
            : null;
        $inventoryTracked = $element->getIsFresh() ? $this->defaultInventoryTracked : $element->inventoryTracked;

        return Group::make($this->uid, [
            Field::make(t('Track Inventory', category: 'commerce'))
                ->status($status, $statusLabel)
                ->width(FieldWidth::Half)
                ->control(
                    Lightswitch::make('inventoryTracked')
                        ->size('small')
                        ->value($inventoryTracked)
                        ->mode($context->mode)
                        ->reactive(),
                ),
            Field::make(t('Allow out of stock purchases', category: 'commerce'))
                ->status($status, $statusLabel)
                ->visible($inventoryTracked)
                ->width(FieldWidth::Half)
                ->control(
                    Lightswitch::make('allowOutOfStockPurchases')
                        ->size('small')
                        ->value($element->getIsFresh() ? $this->defaultAllowOutOfStockPurchases : $element->getIsOutOfStockPurchasingAllowed())
                        ->mode($context->mode)
                        ->reactive(),
                ),
        ])
            ->asField()
            ->label($this->label !== null && $this->label !== $this->defaultLabel() ? $this->label() : null)
            ->instructions($this->instructionsText($element))
            ->instructionsPosition($this->instructionsPosition)
            ->tip($this->tipText($element))
            ->warning($this->warningText($element))
            ->layoutUid($this->uid)
            ->width($this->width);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        // If this is a revision get the canonical element to show the stock for.
        // @TODO Re-evaluate swapping in the canonical element once revisions support tracking inventory independently
        if ($element->getIsRevision()) {
            /** @var Purchasable $element */
            $element = $element->getCanonical();
        }

        $inventoryItemTrackedId = sprintf('store-inventory-item-tracked-%s', mt_rand());
        $storeInventoryTrackedLightswitchConfig = [
            'id' => 'store-inventory-item-tracked',
            'name' => 'inventoryTracked',
            'small' => true,
            'on' => $element->getIsFresh() ? $this->defaultInventoryTracked : $element->inventoryTracked,
            'toggle' => $inventoryItemTrackedId,
            'disabled' => $static,
        ];

        $storeAllowOutOfStockPurchasesLightswitchConfig = [
            'label' => t('Allow out of stock purchases', category: 'commerce'),
            'id' => 'store-backorder-allowed',
            'name' => 'allowOutOfStockPurchases',
            'small' => true,
            'on' => $element->getIsFresh() ? $this->defaultAllowOutOfStockPurchases : $element->getIsOutOfStockPurchasingAllowed(),
            'disabled' => $static,
        ];

        return Html::beginTag('div') .
            FormFields::lightswitchFromConfig($storeInventoryTrackedLightswitchConfig)->toHtml() .
            Html::beginTag('div', ['id' => $inventoryItemTrackedId, 'class' => 'hidden']) .
            FormFields::lightswitchFieldHtml($storeAllowOutOfStockPurchasesLightswitchConfig) .
            Html::endTag('div') .
            Html::endTag('div');
    }

    #[Override]
    protected function settingsNodes(UiContext $context): array
    {
        return [
            ...parent::settingsNodes($context),
            Field::make(t('Track Inventory', category: 'commerce'), Lightswitch::make('defaultInventoryTracked')
                ->value($this->defaultInventoryTracked)),
            Field::make(t('Allow out of stock purchases', category: 'commerce'), Lightswitch::make('defaultAllowOutOfStockPurchases')
                ->value($this->defaultAllowOutOfStockPurchases)),
        ];
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Track Inventory', category: 'commerce');
    }
}
