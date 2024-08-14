<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Transfer;
use craft\commerce\models\InventoryLevel;
use craft\commerce\Plugin;
use craft\commerce\web\assets\transfers\TransfersAsset;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use yii\base\InvalidArgumentException;

/**
 * TransferManagementField represents a field that can be included within a transfer’s field layout designer to manage the transfer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class TransferManagementField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public ?string $label = 'Transfer Management';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'transfer-management';

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Transfer) {
            throw new InvalidArgumentException('TransferLocationsField can only be used in transfer field layouts.');
        }

        if ($static) {
            return self::renderStaticFieldHtml($element);
        } else {
            return self::renderFieldHtml($element);
        }
    }

    public static function renderStaticFieldHtml(Transfer $element, bool $static = false): string
    {
        $html = '';
        $currentUser = Craft::$app->getUser()->getIdentity();

        $origin = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($element->originLocationId);
        $destination = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($element->destinationLocationId);

        $html .= Html::tag('div',
            Html::tag('div',
                Cp::elementCardHtml($origin->getAddress()), ['class' => 'flex-grow']) .
            Html::tag('div',
                Cp::elementCardHtml($destination->getAddress()), ['class' => 'flex-grow'])
            , ['class' => 'flex']);


        $tableRows = '';

        foreach ($element->getDetails() as $detail) {
            $purchasable = $detail->getInventoryItem()?->getPurchasable();
            $tableRows .= Html::tag('tr',
                Html::tag('td', ($purchasable ? Cp::chipHtml($purchasable, ['showActionMenu' => !$purchasable->getIsDraft() && $purchasable->canSave($currentUser)]) : Html::tag('span', $detail->inventoryItemDescription))) .
                Html::tag('td', (string)$detail->quantityRejected, ['class' => 'rightalign']) .
                Html::tag('td', (string)$detail->quantityAccepted, ['class' => 'rightalign']) .
                Html::tag('td', $detail->getReceived() . '/' . $detail->quantity, ['class' => 'rightalign'])
            );
        };

        $totalRow = Html::tag('tr',
            Html::tag('td') .
            Html::tag('td', '') .
            Html::tag('td', '') .
            Html::tag('td', Craft::t('commerce', 'Total ') . ' ' . $element->getTotalReceived() . '/' . $element->getTotalQuantity(), ['class' => 'rightalign'])
        );

        $table = Html::tag('table',
            Html::tag('thead',
                Html::tag('tr',
                    Html::tag('th', Craft::t('commerce', 'Inventory Item')) .
                    Html::tag('th', Craft::t('commerce', 'Rejected'), ['class' => 'rightalign', 'style' => "width: 20%;"]) .
                    Html::tag('th', Craft::t('commerce', 'Accepted'), ['class' => 'rightalign', 'style' => "width: 20%;"]) .
                    Html::tag('th', Craft::t('commerce', 'Total'), ['class' => 'rightalign', 'style' => "width: 20%;"])
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

        $currentUser = Craft::$app->getUser()->getIdentity();
        $view = Craft::$app->getView();
        $inventoryLocationOptions = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocationsAsList(false);
        $isHtmxRequest = Craft::$app->getRequest()->getHeaders()->has('HX-Request');

        $allLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $defaultFirstLocation = $allLocations->first();
        $defaultSecondLocation = $allLocations->skip(1)->first();

        Craft::$app->getView()->registerAssetBundle(TransfersAsset::class);

        $namespacedId = $view->namespaceInputId('transfer-management');

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
            'label' => Craft::t('commerce', 'Origin'),
            'name' => 'originLocationId', // 'name' => 'fields[locations][originLocationId]
            'options' => $inventoryLocationOptions,
            'errors' => $element->getErrors('originLocationId'),
            'value' => $element->originLocationId ?? $defaultFirstLocation->id,
            'inputAttributes' => [
                'hx' => [
                    'post' => '',
                    'trigger' => 'change',
                ],
            ],
        ];

        $destinationLocationSelectFieldConfig = [
            'label' => Craft::t('commerce', 'Destination'),
            'name' => 'destinationLocationId',
            'errors' => $element->getErrors('destinationLocationId'),
            'options' => $inventoryLocationOptions,
            'value' => $element->destinationLocationId ?? $defaultSecondLocation->id,
            'inputAttributes' => [
                'hx' => [
                    'post' => '',
                    'trigger' => 'change',
                ],
            ],
        ];

        $destinationLocationSelectField = Html::tag('div', Cp::selectFieldHtml($destinationLocationSelectFieldConfig), ['class' => 'flex-grow']);
        $originLocationSelectField = Html::tag('div', Cp::selectFieldHtml($originLocationSelectFieldConfig), ['class' => 'flex-grow']);

        $html .= Html::tag('div', $originLocationSelectField . $destinationLocationSelectField, ['class' => 'flex']);

        $tableRows = '';
        $loop = 1;

        foreach ($element->getDetails() as $detail) {
            $key = $detail->uid ?? StringHelper::UUID();
            $purchasable = $detail->getInventoryItem()?->getPurchasable();
            $tableRows .= Html::tag('tr',
                Html::hiddenInput('details[' . $key . '][id]', (string)$detail->id) .
                Html::hiddenInput('details[' . $key . '][uid]', $detail->uid) .
                Html::hiddenInput('details[' . $key . '][inventoryItemId]', (string)$detail->inventoryItemId) .
                Html::tag('td', ($purchasable ? Cp::chipHtml($purchasable, ['showActionMenu' => !$purchasable->getIsDraft() && $purchasable->canSave($currentUser)]) : Html::tag('span', $detail->inventoryItemDescription))) .
                Html::tag('td', Html::input('number', 'details[' . $key . '][quantity]', (string)$detail->quantity, ['class' => 'text fullwidth','hx-post'=>''])) .
                Html::tag('td', Html::a('', '#', [
                    'hx' => [
                        'post' => '',
                        'trigger' => 'click',
                        'vals' => [
                            'removeInventoryItemUid' => $key,
                        ],
                    ],
                    'class' => 'delete icon',
                    'title' => Craft::t('app', 'Delete'),
                    'aria-label' => Craft::t('app', 'Delete'),
                    'role' => 'button',
                ]), ['class' => 'thin'])
            );
        };

        // sum row
        $tableRows .= Html::tag('tr',
            Html::tag('td') .
            Html::tag('td', $element->sumDetailsQuanity() . ' ' . Craft::t('commerce', 'Total')) .
            Html::tag('td',)
        );

        $table = Html::tag('table',
            Html::tag('thead',
                Html::tag('tr',
                    Html::tag('th', Craft::t('commerce', 'Inventory Item')) .
                    Html::tag('th', Craft::t('commerce', 'Quantity'), ['style' => "width: 20%;"]) .
                    Html::tag('th', '')
                )
            ) .
            Html::tag('tbody', $tableRows)
            , ['class' => 'data fullwidth']
        );

        $html .= Cp::fieldHtml($table, [
            'label' => Craft::t('commerce', 'Transfer Items'),
        ]);

        if ($element->originLocationId) {
            $sourceLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($element->originLocationId);
        } else {
            $sourceLocation = $defaultFirstLocation;
        }

        $inventoryLevels = Plugin::getInstance()->getInventory()->getInventoryLocationLevels($sourceLocation)->sortByDesc([
            fn(InventoryLevel $level) => $level->onHandTotal,
        ]);
        $inventoryItemOptions = [];


        /** @var InventoryLevel $level */
        foreach ($inventoryLevels as $level) {
            $inventoryItemOptions[] = [
                'label' => $level->getInventoryItem()->getSku() . ' (' . ($level->onHandTotal ? $level->onHandTotal . ' ' . Craft::t('commerce', 'on hand') : Craft::t('commerce', 'None on hand')) . ')',
                'value' => $level->getInventoryItem()->id,
                'disabled' => !($level->onHandTotal > 0),
            ];
        }

        Craft::$app->getView()->startJsBuffer();

        $addToItems = Html::tag('div',

            Cp::selectizeHtml([
                'name' => 'newInventoryItemId',
                'options' => $inventoryItemOptions,
                'value' => '',
                'placeholder' => Craft::t('commerce', 'Select an item'),
            ]) .

            Html::button(Craft::t('commerce', 'Add an item'), [
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
        $fieldJs = (string)$view->clearJsBuffer(false);

        if ($fieldJs) {
            if ($isHtmxRequest) {
                $html .= html::tag('script', $fieldJs, ['type' => 'text/javascript']);
            } else {
                $view->registerJs($fieldJs);
            }
        }

        return $html . Html::endTag('div');
    }
}
