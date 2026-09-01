<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use craft\commerce\web\assets\inventory\InventoryAsset;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\Models\InventoryLevel;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\currentUser;
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

        // TODO: this still registers the legacy `craft\commerce\web\assets\inventory\InventoryAsset`
        // yii2 AssetBundle via the yii2-adapter bridge, since Commerce's own webpack-built CP assets
        // haven't been ported to a native `HtmlStack`-based registration mechanism yet.
        \Craft::$app->getView()->registerAssetBundle(InventoryAsset::class);

        $totalStock = $element->getStock();
        $inventoryLevels = app(Inventory::class)->getInventoryLevelsForPurchasable($element);

        $availableStockLabel = t('{total} saleable across {locationCount} location(s)', [
            'total' => $totalStock,
            'locationCount' => $inventoryLevels->count(),
        ], category: 'commerce');

        $editInventoryItemId = sprintf('action-edit-inventory-item-%s', mt_rand());
        HtmlStack::jsWithVars(fn($id, $settings) => <<<JS
$('#' + $id).on('click', (e) => {
  e.preventDefault();
  const slideout = new Craft.CpScreenSlideout('commerce/inventory/item-edit', $settings);
});
JS, [
            InputNamespace::namespaceId($editInventoryItemId),
            ['params' => ['inventoryItemId' => $element->getInventoryItem()->id]],
        ]);

        $inventoryLevelTableRows = '';
        /** @var InventoryLevel $inventoryLevel */
        foreach ($inventoryLevels as $inventoryLevel) {
            // Update the quantity button
            $editUpdateQuantityInventoryItemId = sprintf('action-update-qty-%s', mt_rand());
            $updatedValueId = sprintf('updated-value-%s', mt_rand());
            $settings = [
                'params' => [
                    'inventoryLocationId' => $inventoryLevel->getInventoryLocation()->id,
                    'ids[]' => [$element->inventoryItemId],
                    'type' => 'available',
                ],
            ];

            HtmlStack::jsWithVars(fn($id, $updatedValueId, $settings) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
  const slideout = new Craft.Commerce.UpdateInventoryLevelModal($settings);
  slideout.on('submit', (e) => {
    if(e.response.data.updatedItems.length > 0 && e.response.data.updatedItems[0].availableTotal !== undefined) {
      $('#' + $updatedValueId).html(e.response.data.updatedItems[0].availableTotal);
    }
  });
});
JS, [
                InputNamespace::namespaceId($editUpdateQuantityInventoryItemId),
                InputNamespace::namespaceId($updatedValueId),
                $settings,
            ]);

            $inventoryLevelTableRows .= Html::beginTag('tr') .
                Html::beginTag('td') .
                htmlspecialchars($inventoryLevel->getInventoryLocation()->getUiLabel(), ENT_QUOTES) .
                Html::endTag('td') .
                Html::beginTag('td') .
                Html::beginTag('div', ['class' => 'flex']) .
                Html::tag('div', (string)$inventoryLevel->availableTotal, [
                    'id' => $updatedValueId,
                ]) .
                (!$static ? Html::tag('div', Html::button('',
                    [
                        'class' => 'btn menubtn action-btn',
                        'id' => $editUpdateQuantityInventoryItemId,
                    ])) : '') .
                Html::endTag('div') .
                Html::endTag('td') .
                (!$static ? Html::beginTag('td') .
                    (currentUser()?->can('commerce-manageInventoryStockLevels') ?
                        Html::a(
                            t('Manage', category: 'commerce'),
                            Url::cpUrl('commerce/inventory/levels/' . $inventoryLevel->getInventoryLocation()->handle, [
                                'inventoryItemId' => $inventoryLevel->getInventoryItem()->id,
                            ]),
                            [
                                'target' => '_blank',
                                'class' => 'btn small',
                                'id' => $editUpdateQuantityInventoryItemId,
                                'aria-label' => t('Open in a new tab'),
                                'data-icon' => 'external',
                            ]
                        ) : '') : '') .
                Html::endTag('td') .
                Html::endTag('tr');
        }

        $inventoryLevelsTable = Html::beginTag('table', ['class' => 'data fullwidth', 'style' => 'margin-top:5px;']) .
            Html::beginTag('thead') .
            Html::beginTag('tr') .
            Html::beginTag('th') .
            t('Location', category: 'commerce') .
            Html::endTag('th') .
            Html::beginTag('th') .
            t('Available', category: 'commerce') .
            Html::endTag('th') .

            (!$static ? Html::beginTag('th') .
                t('Manage', category: 'commerce') .
                Html::endTag('th') : '') .

            Html::endTag('tr') .
            Html::endTag('thead') .

            Html::beginTag('tbody') .
            $inventoryLevelTableRows .
            Html::beginTag('tr') .
            Html::beginTag('td', ['colspan' => '2']) .
            $availableStockLabel .
            Html::endTag('td') .

            (!$static ? Html::beginTag('td') .
                Html::a(
                    t('Edit', category: 'commerce'),
                    '#',
                    [
                        'class' => 'btn small',
                        'id' => $editInventoryItemId,
                        'aria-label' => t('Edit Inventory Item'),
                        'data-icon' => 'edit',
                    ]
                ) .
                Html::endTag('td') : '') .

            Html::endTag('tr') .
            Html::endTag('tbody') .
            Html::endTag('table');

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
            $inventoryLevelsTable .
            FormFields::lightswitchFieldHtml($storeAllowOutOfStockPurchasesLightswitchConfig) .
            Html::endTag('div') .
            Html::endTag('div');
    }

    protected function settingsHtml(): ?string
    {
        $lightSwitches = FormFields::lightswitchFromConfig([
            'id' => 'defaultInventoryTracked',
            'name' => 'defaultInventoryTracked',
            'label' => t('Track Inventory', category: 'commerce'),
            'on' => $this->defaultInventoryTracked,
        ])->toHtml() .
            FormFields::lightswitchFromConfig([
                'id' => 'defaultAllowOutOfStockPurchases',
                'name' => 'defaultAllowOutOfStockPurchases',
                'label' => t('Allow out of stock purchases', category: 'commerce'),
                'on' => $this->defaultAllowOutOfStockPurchases,
            ])->toHtml();

        return parent::settingsHtml() . FormFields::fieldHtml($lightSwitches, ['label' => t('Default Value')]);
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Track Inventory', category: 'commerce');
    }
}
