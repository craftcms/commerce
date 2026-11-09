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
use CraftCms\Commerce\Stats\TopProducts as TopProductsStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class TopProducts extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    /**
     * Options 'revenue', 'qty'.
     */
    public ?string $type = null;

    /** @var string[]|null */
    public ?array $revenueOptions = [
        TopProductsStat::REVENUE_OPTION_DISCOUNT,
        TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
        TopProductsStat::REVENUE_OPTION_TAX,
        TopProductsStat::REVENUE_OPTION_SHIPPING,
    ];

    private TopProductsStat $stat;

    private string $title;

    /** @var array<string, string> */
    private array $typeOptions;

    /** @var array<int, array{value: string, label: string}> */
    private array $revenueCheckboxOptions;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->typeOptions = [
            TopProductsStat::TYPE_QTY => t('Qty', category: 'commerce'),
            TopProductsStat::TYPE_REVENUE => t('Revenue', category: 'commerce'),
        ];

        $this->revenueCheckboxOptions = [
            ['value' => TopProductsStat::REVENUE_OPTION_DISCOUNT, 'label' => t('{label} — {description}', ['label' => t('Discount', category: 'commerce'), 'description' => t('Include line item discounts.', category: 'commerce')], category: 'commerce')],
            ['value' => TopProductsStat::REVENUE_OPTION_TAX_INCLUDED, 'label' => t('{label} — {description}', ['label' => t('Tax (inc)', category: 'commerce'), 'description' => t('Include built-in line item tax.', category: 'commerce')], category: 'commerce')],
            ['value' => TopProductsStat::REVENUE_OPTION_TAX, 'label' => t('{label} — {description}', ['label' => t('Tax', category: 'commerce'), 'description' => t('Include separate line item tax.', category: 'commerce')], category: 'commerce')],
            ['value' => TopProductsStat::REVENUE_OPTION_SHIPPING, 'label' => t('{label} — {description}', ['label' => t('Shipping', category: 'commerce'), 'description' => t('Include line item shipping costs.', category: 'commerce')], category: 'commerce')],
        ];

        $this->title = match ($this->type) {
            'revenue' => t('Top Products by Revenue', category: 'commerce'),
            'qty' => t('Top Products by Qty Sold', category: 'commerce'),
            default => t('Top Products', category: 'commerce'),
        };

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TopProductsStat(
            $this->dateRange,
            $this->type,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->revenueOptions,
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
        return t('Top Products', category: 'commerce');
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
        $defaultRevenueOptions = [
            TopProductsStat::REVENUE_OPTION_DISCOUNT,
            TopProductsStat::REVENUE_OPTION_TAX_INCLUDED,
            TopProductsStat::REVENUE_OPTION_TAX,
            TopProductsStat::REVENUE_OPTION_SHIPPING,
        ];
        $revenueColumnHandle = 'revenue';
        if ($this->type === TopProductsStat::TYPE_REVENUE && count(array_intersect($defaultRevenueOptions, $this->revenueOptions)) !== count($defaultRevenueOptions)) {
            $revenueColumnHandle = 'revenue_custom';
        }

        return [
            'columns' => [
                ['key' => 'title', 'label' => t('Name', category: 'commerce')],
                ['key' => $this->type, 'label' => $this->typeOptions[$this->type] ?? ''],
            ],
            'rows' => array_map(fn(array $stat) => [
                'title' => isset($stat['product']) ? $stat['product']->title : $stat['title'],
                'url' => ($stat['product'] ?? null)?->getCpEditUrl(),
                'qty' => $stat['qty'],
                'revenue' => $this->formatCurrency($stat[$revenueColumnHandle] ?? $stat['revenue']),
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
            Field::make(t('Revenue Options', category: 'commerce'))
                ->instructions(t('Which values should be included when calculating revenue?', category: 'commerce'))
                ->control(Choice::make('revenueOptions')->multiple()->value($this->revenueOptions)->options($this->revenueCheckboxOptions)),
            ...$this->statSettingsFields(),
        ]);
    }
}
