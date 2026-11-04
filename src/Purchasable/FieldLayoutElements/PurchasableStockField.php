<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Inventory\Data\InventoryLevel;
use CraftCms\Commerce\Inventory\Inventory;
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

        $static = $context->mode !== ControlMode::Editable || $context->ui->mode !== ControlMode::Editable;
        $inventoryTracked = $element->getIsFresh() ? $this->defaultInventoryTracked : $element->inventoryTracked;

        return Group::make($this->uid, [
            Field::make(t('Track Inventory', category: 'commerce'))
                ->width(FieldWidth::Full)
                ->control(
                    Lightswitch::make('inventoryTracked')
                        ->size('small')
                        ->value($inventoryTracked)
                        ->mode($context->mode)
                        ->reactive(),
                ),
            ...($inventoryTracked ? $this->inventoryNodes($element, $static) : []),
            Field::make(t('Allow out of stock purchases', category: 'commerce'))
                ->visible($inventoryTracked)
                ->width(FieldWidth::Full)
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
            ->instructions($this->instructionsText($element, $static))
            ->instructionsPosition($this->instructionsPosition)
            ->tip($this->tipText($element, $static))
            ->warning($this->warningText($element, $static))
            ->layoutUid($this->uid)
            ->width($this->width);
    }

    /**
     * A read-only summary of the purchasable's available stock at each inventory location, with a
     * “Manage” link on each row that opens its inventory item in a slideout.
     *
     * @return list<Node>
     */
    private function inventoryNodes(Purchasable $purchasable, bool $static): array
    {
        if (!$purchasable->id || $purchasable->getIsFresh() || !$purchasable->inventoryItemId) {
            return [
                MarkdownContent::make($this->uid . '-inventory-unsaved', t('Inventory can be managed once this {type} is saved.', [
                    'type' => $purchasable::lowerDisplayName(),
                ], category: 'commerce'))->displayInPane(false),
            ];
        }

        $inventoryLevels = app(Inventory::class)->getInventoryLevelsForPurchasable($purchasable);
        $manageUrl = !$static && currentUser()?->can('commerce-manageInventoryStockLevels')
            ? Url::cpUrl('commerce/inventory/item/' . $purchasable->inventoryItemId)
            : null;

        $table = Table::make($this->uid . '-inventory-levels')
            ->columns([
                ['key' => 'location', 'label' => t('Location', category: 'commerce')],
                ['key' => 'available', 'label' => t('Available', category: 'commerce')],
                ...($manageUrl ? [['key' => 'manage', 'label' => '']] : []),
            ])
            ->rows($inventoryLevels->map(fn(InventoryLevel $inventoryLevel) => [
                'location' => $inventoryLevel->getInventoryLocation()->getUiLabel(),
                'available' => $inventoryLevel->availableTotal,
                ...($manageUrl ? ['manage' => ['label' => t('Manage', category: 'commerce'), 'url' => $manageUrl, 'slideout' => true]] : []),
            ])->values()->all())
            ->emptyMessage(t('No inventory locations exist for this store.', category: 'commerce'));

        return [
            $table,
            MarkdownContent::make($this->uid . '-inventory-total', t('{total} saleable across {count, plural, =1{# location} other{# locations}}', [
                'total' => $purchasable->getStock(),
                'count' => $inventoryLevels->count(),
            ], category: 'commerce'))->displayInPane(false),
        ];
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
