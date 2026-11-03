<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TopProductTypes as TopProductTypesStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class TopProductTypes extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    /**
     * Options 'revenue', 'qty'.
     */
    public ?string $type = null;

    private TopProductTypesStat $stat;

    private string $title;

    /** @var array<string, string> */
    private array $typeOptions;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->typeOptions = [
            'qty' => t('Qty', category: 'commerce'),
            'revenue' => t('Revenue', category: 'commerce'),
        ];

        $this->title = match ($this->type) {
            'revenue' => t('Top Product Types by Revenue', category: 'commerce'),
            'qty' => t('Top Product Types by Qty Sold', category: 'commerce'),
            default => t('Top Product Types', category: 'commerce'),
        };

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TopProductTypesStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId,
        );

        if (!empty($this->orderStatuses)) {
            $this->stat->setOrderStatuses($this->orderStatuses);
        }
    }

    #[\Override]
    public static function isSelectable(): bool
    {
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Top Product Types', category: 'commerce');
    }

    #[\Override]
    public function getTitle(): ?string
    {
        return $this->title;
    }

    #[\Override]
    public function getSubtitle(): ?string
    {
        return $this->stat->getDateRangeWording();
    }

    #[\Override]
    public function component(): ?string
    {
        return 'commerce:widget-table';
    }

    #[\Override]
    public function props(): array
    {
        return [
            'columns' => [
                ['key' => 'title', 'label' => t('Name', category: 'commerce')],
                ['key' => $this->type, 'label' => $this->typeOptions[$this->type] ?? ''],
            ],
            'rows' => array_map(fn(array $stat) => [
                'title' => isset($stat['productType']) ? $stat['productType']->name : $stat['name'],
                'qty' => $stat['qty'],
                'revenue' => $this->formatCurrency($stat['revenue']),
            ], $this->stat->get() ?: []),
        ];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make([
            Field::make(t('Type', category: 'commerce'))
                ->control(Choice::make('type')->value($this->type)->options(
                    collect($this->typeOptions)->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values()->all()
                )),
            ...$this->statSettingsFields(),
        ]);
    }
}
