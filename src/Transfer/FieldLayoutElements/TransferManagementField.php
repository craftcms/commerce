<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\FieldLayoutElements;

use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Enums\FieldWidth;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Form\Nodes\TemplateContent;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Form\Controls\TransferDetails;
use CraftCms\Commerce\Inventory\Data\InventoryLevel;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use InvalidArgumentException;
use Override;

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

    #[Override]
    public function formNode(FieldLayoutElementContext $context): ?Node
    {
        $transfer = $context->element;

        if (!$transfer instanceof Transfer) {
            throw new InvalidArgumentException('TransferManagementField can only be used in transfer field layouts.');
        }

        $uid = $this->uid ?? $this->attribute;

        if ($context->mode !== ControlMode::Editable || !$transfer->isTransferDraft()) {
            return TemplateContent::make($uid, self::renderStaticFieldHtml($transfer));
        }

        $locations = app(InventoryLocations::class)->getAllInventoryLocations();
        $locationOptions = $locations
            ->map(fn(InventoryLocation $location) => ['label' => $location->getUiLabel(), 'value' => (string)$location->id])
            ->values()
            ->all();
        $originLocationId = $transfer->originLocationId ?? $locations->first()?->id;
        $destinationLocationId = $transfer->destinationLocationId ?? $locations->skip(1)->first()?->id;

        return Group::make($uid, [
            Field::make(
                t('Origin', category: 'commerce'),
                Choice::make('originLocationId')
                    ->options($locationOptions)
                    ->value($originLocationId !== null ? (string)$originLocationId : null)
                    ->reactive(),
            )->width(FieldWidth::Half)->required(),
            Field::make(
                t('Destination', category: 'commerce'),
                Choice::make('destinationLocationId')
                    ->options($locationOptions)
                    ->value($destinationLocationId !== null ? (string)$destinationLocationId : null),
            )->width(FieldWidth::Half)->required(),
            Field::make(
                t('Transfer Items', category: 'commerce'),
                TransferDetails::make('details')
                    ->value(self::detailsValue($transfer))
                    ->itemsHtml(self::detailsItemsHtml($transfer))
                    ->options(self::inventoryItemOptions($originLocationId))
                    ->reactive(),
            )->required(),
        ]);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Transfer) {
            throw new InvalidArgumentException('TransferManagementField can only be used in transfer field layouts.');
        }

        return self::renderStaticFieldHtml($element);
    }

    /**
     * @return array<string, array{id: ?int, uid: string, inventoryItemId: ?int, quantity: int}>
     */
    private static function detailsValue(Transfer $transfer): array
    {
        $value = [];

        foreach ($transfer->getDetails() as $detail) {
            $uid = $detail->uid ?? (string)Str::uuid();
            $value[$uid] = [
                'id' => $detail->id,
                'uid' => $uid,
                'inventoryItemId' => $detail->inventoryItemId,
                'quantity' => $detail->quantity,
            ];
        }

        return $value;
    }

    /**
     * TODO: Show the purchasable's element chip again once variant authorization no longer recurses
     * (`Variant::canSave()` → `parent::canSave()` → Gate → `Variant::canSave()`).
     *
     * @return array<int, string>
     */
    private static function detailsItemsHtml(Transfer $transfer): array
    {
        $html = [];

        foreach ($transfer->getDetails() as $detail) {
            $html[$detail->inventoryItemId] = e($detail->inventoryItemDescription);
        }

        return $html;
    }

    /**
     * The origin location's inventory items, most on hand first. Items with none on hand can't be transferred.
     *
     * @return list<array{label: string, value: string, disabled: bool}>
     */
    private static function inventoryItemOptions(?int $originLocationId): array
    {
        $origin = $originLocationId ? app(InventoryLocations::class)->getInventoryLocationById($originLocationId) : null;

        if ($origin === null) {
            return [];
        }

        return app(Inventory::class)->getInventoryLocationLevels($origin)
            ->sortByDesc(fn(InventoryLevel $level) => $level->onHandTotal)
            ->map(fn(InventoryLevel $level) => [
                'label' => $level->getInventoryItem()->getSku() . ' (' . ($level->onHandTotal ? $level->onHandTotal . ' ' . t('on hand', category: 'commerce') : t('None on hand', category: 'commerce')) . ')',
                'value' => (string)$level->getInventoryItem()->id,
                'disabled' => !($level->onHandTotal > 0),
            ])
            ->values()
            ->all();
    }

    public static function renderStaticFieldHtml(Transfer $element): string
    {
        $html = '';

        $locationCards = '';

        foreach ([$element->getOriginLocation(), $element->getDestinationLocation()] as $location) {
            $locationCards .= Html::tag('div',
                $location ? app(ElementHtml::class)->elementCardHtml($location->getAddress()) : '', ['class' => 'flex-grow']);
        }

        $html .= Html::tag('div', $locationCards, ['class' => 'flex']);

        $tableRows = '';

        // TODO: Show the purchasable's element chip again once variant authorization no longer recurses.
        foreach ($element->getDetails() as $detail) {
            $tableRows .= Html::tag('tr',
                Html::tag('td', Html::tag('span', e($detail->inventoryItemDescription))) .
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
}
