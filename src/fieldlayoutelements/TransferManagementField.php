<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fieldlayoutelements;

use craft\base\ElementInterface;
use craft\commerce\elements\Transfer;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\Plugin;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\InvalidArgumentException;
use Craft;

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
    public ?string $label = 'Locations';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'locations';


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

        $inventoryLocationOptions = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->mapWithKeys(function($location) {
            return [$location->id => $location->name];
        })->toArray();

        $originLocationSelectFieldConfig = [
            'label' => Craft::t('commerce', 'Origin'),
            'name' => 'originLocationId', // 'name' => 'fields[locations][originLocationId]
            'options' => $inventoryLocationOptions,
            'value' => $element->originLocationId,
            'containerAttributes' => [
                'class' => ['flex-grow']
            ]
        ];

        $destinationLocationSelectFieldConfig = [
            'label' => Craft::t('commerce', 'Destination'),
            'name' => 'destinationLocationId',
            'options' => $inventoryLocationOptions,
            'value' => $element->destinationLocationId,
            'containerAttributes' => [
                'class' => ['flex-grow']
            ]
        ];

        if ($element->transferStatus != TransferStatusType::DRAFT) {
            $originLocationSelectFieldConfig['inputAttributes']['disabled'] = true;
            $destinationLocationSelectFieldConfig['inputAttributes']['disabled'] = true;
        }

        $destinationLocationSelectField = Cp::selectFieldHtml($destinationLocationSelectFieldConfig);
        $originLocationSelectField = Cp::selectFieldHtml($originLocationSelectFieldConfig);


        $div = Html::tag('div', $originLocationSelectField . $destinationLocationSelectField, ['class' => 'flex']);

        return \craft\helpers\Cp::fieldHtml($div, [
            'instructions' => Craft::t('commerce','The locations between which the stock is being transferred.'),
        ]);
    }
}
