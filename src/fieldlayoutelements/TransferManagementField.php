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
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
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
     * @param ?TransferDetail $detail
     * @return string
     */
    public function getDetailRow(?TransferDetail $detail = null, bool $disabled = false): string
    {
        $index = uniqid();
        return Html::beginTag('tr') .
            Html::beginTag('td') .
            Cp::textFieldHtml([
                'name' => "details[$index][inventoryItemId]",
                'value' => $detail?->inventoryItemId,
                'disabled' => $disabled,
            ]) .
            Html::endTag('td') .
            Html::beginTag('td') .
            Cp::textFieldHtml([
                'type' => 'number',
                'name' => "details[$index][quantity]",
                'value' => $detail?->quantity,
                'disabled' => $disabled,
            ]) .
            Html::endTag('td') .
            Html::endTag('tr');
    }


    /**
     * @inheritdoc
     */
    protected function selectorInnerHtml(): string
    {
        return
            Html::tag('span', '', [
                'class' => ['fld-product-title-field-icon', 'fld-field-hidden', 'hidden'],
            ]) .
            parent::selectorInnerHtml();
    }

    /**
     * @inheritdoc
     */
    public function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Transfer) {
            throw new InvalidArgumentException('TransferLocationsField can only be used in transfer field layouts.');
        }

        $inventoryLocationOptions = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations(true)->mapWithKeys(function($location) {
            return [
                $location->id => [
                    'label' => $location->name,
                    'disabled' => false, // Look to disable an item they dont have access to.
                ],
            ];
        })->toArray();

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

        if ($element->transferStatus != TransferStatusType::DRAFT) {
            $originLocationSelectFieldConfig['inputAttributes']['disabled'] = true;
            $destinationLocationSelectFieldConfig['inputAttributes']['disabled'] = true;
        }

        $destinationLocationSelectField = Cp::selectFieldHtml($destinationLocationSelectFieldConfig);
        $originLocationSelectField = Cp::selectFieldHtml($originLocationSelectFieldConfig);

        $div = Html::tag('div', $originLocationSelectField . $destinationLocationSelectField, ['class' => 'flex']);

        $detailRows = '';

        foreach ($element->getDetails() as $detail) {
            $disabled = $element->getTransferStatus() !== TransferStatusType::DRAFT;
            $detailRows .= $this->getDetailRow($detail, $disabled);
        }

        $detailRows .= $this->getDetailRow();

        $detailsTable = Html::beginTag('table', ['class' => 'data fullwidth', 'style' => 'margin-top:5px;']) .
            Html::beginTag('thead') .
            Html::beginTag('tr') .
            Html::beginTag('th') .
            Craft::t('commerce', 'Item') .
            Html::endTag('th') .
            Html::beginTag('th') .
            Craft::t('commerce', 'Quantity') .
            Html::endTag('th') .
            Html::endTag('tr') .
            Html::endTag('thead') .
            Html::beginTag('tbody') .
            $detailRows .
            Html::endTag('tbody') .
            Html::endTag('table');

        return $div . $detailsTable;
    }
}
