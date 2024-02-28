<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\models\InventoryLevel;
use craft\commerce\Plugin;
use craft\commerce\web\assets\inventory\InventoryAsset;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;

/**
 * PurchasableStockField represents a Stock field that is included within a variant field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PurchasableStockField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Inventory';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'stock';

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(InventoryAsset::class);

        /** @var Purchasable|null $element */
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        $view = Craft::$app->getView();

        $totalStock = $element->getAvailableTotalStock();
        $inventoryLevels = Plugin::getInstance()->getInventory()->getInventoryLevelsForPurchasable($element);


        $availableStockLabel = Craft::t('commerce', '{total} available across {locationCount} location(s)', [
            'total' => $totalStock,
            'locationCount' => $inventoryLevels->count(),
        ]);

        $editInventoryItemId = sprintf('action-edit-inventory-item-%s', mt_rand());
        // Register the slideout for editing the inventory item global settings.
        $view->registerJsWithVars(fn($id, $settings) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
  const slideout = new Craft.CpScreenSlideout('commerce/inventory/item-edit', $settings);
});
JS, [
            $view->namespaceInputId($editInventoryItemId),
            ['params' => ['inventoryItemId' => $element->getInventoryItem()->id]],
        ]);

        $inventoryLevelTableRows = '';
        /** @var InventoryLevel $inventoryLevel */
        foreach ($inventoryLevels as $inventoryLevel) {

            // Update the quantity button
            $editUpdateQuantityInventoryItemId = sprintf('action-update-qty-%s', mt_rand());
            $settings = [
                'params' => [
                    'inventoryLocationId' => $inventoryLevel->getInventoryLocation()->id,
                    'ids[]' => [$element->inventoryItemId],
                ],
            ];

            $view->registerJsWithVars(fn($id, $settings) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
  const slideout = new Craft.Commerce.UpdateInventoryLevelModal($settings);
  slideout.on('submit', (e) => {
    console.log(e);
  });
});
JS, [
                $view->namespaceInputId($editUpdateQuantityInventoryItemId),
                $settings,
            ]);

            $inventoryLevelTableRows .= Html::beginTag('tr') .
                Html::beginTag('td') .
                $inventoryLevel->getInventoryLocation()->name .
                Html::endTag('td') .
                Html::beginTag('td') .
                Html::beginTag('div', ['class' => 'flex']) .
                Html::tag('div', (string)$inventoryLevel->availableTotal) .
                Html::tag('div',Html::button(Craft::t('commerce', ''),
                    [
                        'class' => 'btn menubtn action-btn',
                        'id' => $editUpdateQuantityInventoryItemId,
                    ])) .
                Html::endTag('div') .
                Html::endTag('td') .
                Html::beginTag('td') .
                Html::a(
                    Craft::t('commerce', 'Manage'),
                    'commerce/inventory/levels/' . $inventoryLevel->getInventoryLocation()->handle,
                    [
                        'target' => '_blank',
                        'class' => 'btn small',
                        'id' => $editUpdateQuantityInventoryItemId,
                        'aria-label' => Craft::t('app', 'Open in a new tab'),
                        'data-icon' => 'external',
                    ]
                ) .
                Html::endTag('td') .
                Html::endTag('tr');
        }

        $inventoryLevelsTable = Html::beginTag('table', ['class' => 'data fullwidth', 'style' => 'margin-top:5px;']) .
            Html::beginTag('thead') .
            Html::beginTag('tr') .
                Html::beginTag('th') .
                    Craft::t('commerce', 'Location') .
                Html::endTag('th') .
                    Html::beginTag('th') .
                Craft::t('commerce', 'Available') .
                    Html::endTag('th') .
                Html::beginTag('th') .
                    Craft::t('commerce', 'Manage') .
                Html::endTag('th') .
            Html::endTag('tr') .
            Html::endTag('thead') .
            Html::beginTag('tbody') .
            $inventoryLevelTableRows .
            Html::beginTag('tr') .
            Html::beginTag('td', ['colspan' => '2']) .
            $availableStockLabel .
            Html::beginTag('td') .
            Html::a(
                Craft::t('commerce', 'Edit'),
                '#',
                [
                    'class' => 'btn small',
                    'id' => $editInventoryItemId,
                    'aria-label' => Craft::t('app', 'Edit Inventory Item'),
                    'data-icon' => 'edit',
                ]
            ) .
            Html::endTag('td') .
            Html::endTag('td') .
            Html::endTag('tr') .
            Html::endTag('tbody') .
            Html::endTag('table');

        $inventoryItemTrackedId = sprintf('store-inventory-item-tracked-%s', mt_rand());
        $storeInventoryTrackedLightswitchConfig = [
            'id' => 'store-inventory-item-tracked',
            'name' => 'inventoryTracked',
            'small' => true,
            'on' => $element->inventoryTracked,
            'toggle' => $inventoryItemTrackedId,
        ];

        return Html::beginTag('div') .
            Cp::lightswitchHtml($storeInventoryTrackedLightswitchConfig) .
            Html::beginTag('div', ['id' => $inventoryItemTrackedId, 'class' => 'hidden']) .
            $inventoryLevelsTable .
            Html::endTag('div') .
            Html::endTag('div') .
            Html::endTag('div');
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('commerce', 'Track Inventory');
    }
}
