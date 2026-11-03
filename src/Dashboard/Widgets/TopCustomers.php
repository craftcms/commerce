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
use CraftCms\Commerce\Stats\TopCustomers as TopCustomersStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class TopCustomers extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    /**
     * Options 'total', 'average'.
     */
    public ?string $type = null;

    private TopCustomersStat $stat;

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
            'total' => t('Total', category: 'commerce'),
            'average' => t('Average', category: 'commerce'),
        ];

        $this->title = match ($this->type) {
            'average' => t('Top Customers by Average Order', category: 'commerce'),
            'total' => t('Top Customers by Total Revenue', category: 'commerce'),
            default => t('Top Customers', category: 'commerce'),
        };

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TopCustomersStat(
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
        /** @phpstan-ignore-next-line nullsafe.neverNull, nullCoalesce.expr (currentUser() genuinely returns ?CraftUser) */
        return (currentUser()?->can('commerce-manageOrders') ?? false) && (currentUser()?->can('commerce-manageCustomers') ?? false);
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Top Customers', category: 'commerce');
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
                ['key' => 'title', 'label' => t('Email', category: 'commerce')],
                ['key' => $this->type, 'label' => $this->typeOptions[$this->type] ?? ''],
            ],
            'rows' => array_map(fn(array $stat) => [
                'title' => $stat['email'],
                'url' => ($stat['customer'] ?? null)?->getCpEditUrl(),
                'total' => $this->formatCurrency($stat['total']),
                'average' => $this->formatCurrency($stat['average']),
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
