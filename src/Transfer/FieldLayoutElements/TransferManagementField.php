<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\FieldLayoutElements;

use craft\commerce\web\assets\transfers\TransfersAsset;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Inventory\Data\InventoryLevel;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * TransferManagementField represents a field that can be included within a transfer's field layout designer to manage the transfer.
 */
class TransferManagementField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public ?string $label = '__blank__';

    #[Override]
    public bool $required = true;

    #[Override]
    public string $attribute = 'transfer-management';

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Transfer) {
            throw new InvalidArgumentException('TransferManagementField can only be used in transfer field layouts.');
        }

        if ($static) {
            return self::renderStaticFieldHtml($element);
        }

        return self::renderFieldHtml($element);
    }

    public static function renderStaticFieldHtml(Transfer $element): string
    {
        $html = '';
        $currentUser = currentUserElement();

        $origin = app(InventoryLocations::class)->getInventoryLocationById($element->originLocationId);
        $destination = app(InventoryLocations::class)->getInventoryLocationById($element->destinationLocationId);

        $html .= Html::tag('div',
            Html::tag('div',
                app(ElementHtml::class)->elementCardHtml($origin->getAddress()), ['class' => 'flex-grow']) .
            Html::tag('div',
                app(ElementHtml::class)->elementCardHtml($destination->getAddress()), ['class' => 'flex-grow'])
            , ['class' => 'flex']);

        $tableRows = '';

        foreach ($element->getDetails() as $detail) {
            $purchasable = $detail->getInventoryItem()?->getPurchasable(Sites::getCurrentSite()->id);
            $tableRows .= Html::tag('tr',
                Html::tag('td', ($purchasable ? app(ElementHtml::class)->elementChipHtml($purchasable, ['showActionMenu' => !$purchasable->getIsDraft() && $purchasable->canSave($currentUser)]) : Html::tag('span', $detail->inventoryItemDescription))) .
                Html::tag('td', (string)$detail->quantityRejected, ['class' => 'rightalign']) .
                Html::tag('td', (string)$detail->quantityAccepted, ['class' => 'rightalign']) .
                Html::tag('td', $detail->getReceived() . '/' . $detail->quantity, ['class' => 'rightalign'])
            );
        }

        $totalRow = Html::tag('tr',
            Html::tag('td') .
            Html::tag('td', '') .
            Html::tag('td', '') .
            Html::tag('td', t('Total ', category: 'commerce') . ' ' . $element->getTotalReceived() . '/' . $element->getTotalQuantity(), ['class' => 'rightalign'])
        );

        $table = Html::tag('table',
            Html::tag('thead',
                Html::tag('tr',
                    Html::tag('th', t('Inventory Item', category: 'commerce')) .
                    Html::tag('th', t('Rejected', category: 'commerce'), ['class' => 'rightalign', 'style' => 'width: 20%;']) .
                    Html::tag('th', t('Accepted', category: 'commerce'), ['class' => 'rightalign', 'style' => 'width: 20%;']) .
                    Html::tag('th', t('Total', category: 'commerce'), ['class' => 'rightalign', 'style' => 'width: 20%;'])
                )
            ) .
            Html::tag('tbody', $tableRows . $totalRow)
            , ['class' => 'data fullwidth']
        );

        $html .= Html::tag('hr') . $table;

        return $html;
    }

    public static function renderFieldHtml(Transfer $element): string
    {
        // Only draft is editable
        if (!$element->isTransferDraft()) {
            return self::renderStaticFieldHtml($element);
        }

        $currentUser = currentUserElement();
        $inventoryLocationOptions = app(InventoryLocations::class)->getAllInventoryLocationsAsList(false);
        $isHtmxRequest = request()->hasHeader('HX-Request');

        $allLocations = app(InventoryLocations::class)->getAllInventoryLocations();
        $defaultFirstLocation = $allLocations->first();
        $defaultSecondLocation = $allLocations->skip(1)->first();

        // TODO: this still registers the legacy `craft\commerce\web\assets\transfers\TransfersAsset`
        // yii2 AssetBundle via the yii2-adapter bridge, since Commerce's own webpack-built CP assets
        // haven't been ported to a native `HtmlStack`-based registration mechanism yet.
        \Craft::$app->getView()->registerAssetBundle(TransfersAsset::class);

        $namespacedId = InputNamespace::namespaceId('transfer-management');

        $html = Html::beginTag('div', [
            'id' => $namespacedId,
            'hx' => [
                'ext' => 'craft-cp',
                'target' => '#' . $namespacedId,
                'include' => '#' . $namespacedId,
                'vals' => [
                    'action' => 'commerce/transfers/render-management',
                    'transferId' => $element->id,
                ],
            ],
        ]);

        $originLocationSelectFieldConfig = [
            'label' => t('Origin', category: 'commerce'),
            'name' => 'originLocationId',
            'options' => $inventoryLocationOptions,
            'errors' => $element->errors()->get('originLocationId'),
            'value' => $element->originLocationId ?? $defaultFirstLocation->id,
            'inputAttributes' => [
                'hx' => [
                    'post' => '',
                    'trigger' => 'change',
                ],
            ],
        ];

        $destinationLocationSelectFieldConfig = [
            'label' => t('Destination', category: 'commerce'),
            'name' => 'destinationLocationId',
            'errors' => $element->errors()->get('destinationLocationId'),
            'options' => $inventoryLocationOptions,
            'value' => $element->destinationLocationId ?? $defaultSecondLocation->id,
            'inputAttributes' => [
                'hx' => [
                    'post' => '',
                    'trigger' => 'change',
                ],
            ],
        ];

        $destinationLocationSelectField = Html::tag('div', FormFields::selectFieldHtml($destinationLocationSelectFieldConfig), ['class' => 'flex-grow']);
        $originLocationSelectField = Html::tag('div', FormFields::selectFieldHtml($originLocationSelectFieldConfig), ['class' => 'flex-grow']);

        $html .= Html::tag('div', $originLocationSelectField . $destinationLocationSelectField, ['class' => 'flex']);

        $tableRows = '';

        foreach ($element->getDetails() as $detail) {
            $key = $detail->uid ?? (string)Str::uuid();
            $purchasable = $detail->getInventoryItem()?->getPurchasable(Sites::getCurrentSite()->id);
            $tableRows .= Html::tag('tr',
                Html::hiddenInput('details[' . $key . '][id]', (string)$detail->id) .
                Html::hiddenInput('details[' . $key . '][uid]', $detail->uid) .
                Html::hiddenInput('details[' . $key . '][inventoryItemId]', (string)$detail->inventoryItemId) .
                Html::tag('td', ($purchasable ? app(ElementHtml::class)->elementChipHtml($purchasable, ['showActionMenu' => !$purchasable->getIsDraft() && $purchasable->canSave($currentUser)]) : Html::tag('span', $detail->inventoryItemDescription))) .
                Html::tag('td', FormFields::textHtml([
                    'type' => 'number',
                    'name' => 'details[' . $key . '][quantity]',
                    'value' => (string)$detail->quantity,
                    'class' => 'text fullwidth',
                    'errors' => $element->errors()->get('details.' . $key . '.quantity'),
                    'inputAttributes' => [
                        'hx' => [
                            'post' => '',
                        ],
                    ],
                ])) .
                Html::tag('td', Html::a('', '#', [
                    'hx' => [
                        'post' => '',
                        'trigger' => 'click',
                        'vals' => [
                            'removeInventoryItemUid' => $key,
                        ],
                    ],
                    'class' => 'delete icon',
                    'title' => t('Delete'),
                    'aria-label' => t('Delete'),
                    'role' => 'button',
                ]), ['class' => 'thin'])
            );
        }

        // sum row
        $tableRows .= Html::tag('tr',
            Html::tag('td') .
            Html::tag('td', $element->sumDetailsQuanity() . ' ' . t('Total', category: 'commerce')) .
            Html::tag('td')
        );

        $table = Html::tag('table',
            Html::tag('thead',
                Html::tag('tr',
                    Html::tag('th', t('Inventory Item', category: 'commerce')) .
                    Html::tag('th', t('Quantity', category: 'commerce'), ['style' => 'width: 20%;']) .
                    Html::tag('th', '')
                )
            ) .
            Html::tag('tbody', $tableRows)
            , ['class' => 'data fullwidth']
        );

        $html .= FormFields::fieldHtml($table, [
            'label' => t('Transfer Items', category: 'commerce'),
        ]);

        if ($element->originLocationId) {
            $sourceLocation = app(InventoryLocations::class)->getInventoryLocationById($element->originLocationId);
        } else {
            $sourceLocation = $defaultFirstLocation;
        }

        $inventoryLevels = app(Inventory::class)->getInventoryLocationLevels($sourceLocation)->sortByDesc([
            fn(InventoryLevel $level) => $level->onHandTotal,
        ]);
        $inventoryItemOptions = [];

        /** @var InventoryLevel $level */
        foreach ($inventoryLevels as $level) {
            $inventoryItemOptions[] = [
                'label' => $level->getInventoryItem()->getSku() . ' (' . ($level->onHandTotal ? $level->onHandTotal . ' ' . t('on hand', category: 'commerce') : t('None on hand', category: 'commerce')) . ')',
                'value' => $level->getInventoryItem()->id,
                'disabled' => !($level->onHandTotal > 0),
            ];
        }

        HtmlStack::startJsBuffer();

        $addToItems = Html::tag('div',
            FormFields::selectizeHtml([
                'name' => 'newInventoryItemId',
                'options' => $inventoryItemOptions,
                'value' => '',
                'placeholder' => t('Select an item', category: 'commerce'),
            ]) .
            Html::tag('button', t('Add an item', category: 'commerce'), [
                'type' => 'button',
                'class' => 'btn secondary',
                'hx' => [
                    'post' => '',
                    'target' => '#' . $namespacedId,
                    'trigger' => 'click',
                    'vals' => [
                        'addItem' => true,
                    ],
                ],
            ])
            , ['class' => 'flex']);

        $html .= $addToItems;
        $fieldJs = (string)HtmlStack::clearJsBuffer(false);

        if ($fieldJs) {
            if ($isHtmxRequest) {
                $html .= Html::tag('script', $fieldJs, ['type' => 'text/javascript']);
            } else {
                HtmlStack::js($fieldJs);
            }
        }

        return $html . Html::endTag('div');
    }
}
