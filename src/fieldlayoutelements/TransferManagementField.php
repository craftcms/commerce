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
use craft\commerce\elements\Transfer;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\commerce\web\assets\transfers\TransfersAsset;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
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
        return '';
    }

    public static function renderFieldHtml(Transfer $element): string
    {
        $view = Craft::$app->getView();
        $inventoryLocationOptions = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocationsAsList(false);
        $isHtmxRequest = Craft::$app->getRequest()->getHeaders()->has('HX-Request');

        Craft::$app->getView()->registerAssetBundle(TransfersAsset::class);

        $namespacedId = $view->namespaceInputId('transfer-management');

        $html = Html::beginTag('div', [
            'class' => ['transfer-management-main'],
            'hx' => [
                'ext' => 'craft-cp',
            ],
        ]);

        $originLocationSelectFieldConfig = [
            'label' => Craft::t('commerce', 'Origin'),
            'name' => 'originLocationId', // 'name' => 'fields[locations][originLocationId]
            'options' => $inventoryLocationOptions,
            'value' => $element->originLocationId,
            'containerAttributes' => [
                'class' => ['flex-grow'],
            ],
        ];

        $destinationLocationSelectFieldConfig = [
            'label' => Craft::t('commerce', 'Destination'),
            'name' => 'destinationLocationId',
            'options' => $inventoryLocationOptions,
            'value' => $element->destinationLocationId,
            'containerAttributes' => [
                'class' => ['flex-grow'],
            ],
        ];

        $destinationLocationSelectField = Cp::selectFieldHtml($destinationLocationSelectFieldConfig);
        $originLocationSelectField = Cp::selectFieldHtml($originLocationSelectFieldConfig);

        $html .= Html::tag('div', $originLocationSelectField . $destinationLocationSelectField, ['class' => 'flex']);

        $cols = self::getTransferDetailColumns($element);
        $rows = collect($element->getDetails())->mapWithKeys(function (TransferDetail $detail) {
            return [
                $detail->id => [
                    'id' => $detail->id,
                    'inventoryItemId' => $detail->inventoryItemId,
                    'quantity' => $detail->quantity,
                ]
            ];
        })->all();

        $detailsTable = Cp::editableTableFieldHtml([
            'allowAdd' => false,
            'allowReorder' => false,
            'allowDelete' => $element->transferStatus == TransferStatusType::DRAFT,
            'name' => 'details',
            'cols' => $cols,
            'rows' => $rows,
        ]);

        $button = Html::buttonInput(Craft::t('commerce', 'Add an item'), [
            'class' => 'btn dashed add icon',
            'hx-target' => '.transfer-management-main',
            'hx-vals' => Json::encode([
                'action' => 'commerce/transfers/render-management',
                'addRow' => true,
                'transferId' => $element->id,
            ]),
            'hx-post' => '',
            'hx-trigger' => 'click',
        ]);

        $html .= Cp::fieldHtml($detailsTable , [
            'label' => Craft::t('commerce', 'Transfer Items'),
        ]);

        $html .= $button;

        return $html . Html::endTag('div');
    }

    public static function getTransferDetailColumns(Transfer $transfer): array
    {
        $cols = [];

        $cols[] = [
            'type' => 'singleline',
            'label' => Craft::t('commerce', 'Item'),
            'name' => 'inventoryItemId',
            'static' => true,
        ];
        $cols[] = [
            'type' => 'singleline',
            'label' => Craft::t('commerce', 'Quantity'),
            'name' => 'quantity',
        ];

        return $cols;
    }
}
