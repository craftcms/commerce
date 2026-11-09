<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalOrdersByCountry as TotalOrdersByCountryStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class TotalOrdersByCountry extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    /**
     * Options 'billing', 'shipping'.
     */
    public string $type;

    private TotalOrdersByCountryStat $stat;

    private string $title;

    /** @var array<string, string> */
    private array $typeOptions;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->typeOptions = [
            'billing' => t('Billing', category: 'commerce'),
            'shipping' => t('Shipping', category: 'commerce'),
        ];

        if (isset($this->type) && $this->type == 'billing') {
            $this->title = t('Total Orders by Billing Country', category: 'commerce');
        } else {
            $this->title = t('Total Orders by Shipping Country', category: 'commerce');
            $this->type = 'shipping';
        }

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TotalOrdersByCountryStat(
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
    public static function isSelectable(): bool
    {
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Total Orders by Country', category: 'commerce');
    }

    #[\Override]
    public function component(): ?string
    {
        return 'commerce:widget-chart';
    }

    #[\Override]
    public function props(): array
    {
        $stats = $this->stat->get() ?: [];

        return [
            'type' => 'doughnut',
            'labels' => Arr::pluck($stats, 'name'),
            'interval' => null,
            'currency' => app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso(),
            'showLegend' => true,
            'datasets' => [
                ['label' => t('Order count', category: 'commerce'), 'data' => array_map(intval(...), Arr::pluck($stats, 'total')), 'format' => 'number'],
            ],
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
