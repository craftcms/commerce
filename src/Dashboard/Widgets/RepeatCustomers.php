<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\RepeatCustomers as RepeatingCustomersStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\t;

class RepeatCustomers extends CommerceWidget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    private RepeatingCustomersStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new RepeatingCustomersStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId,
        );

        if (!empty($this->orderStatuses)) {
            $this->stat->setOrderStatuses($this->orderStatuses);
        }
    }

    #[\Override]
    protected static function requiredPermissions(): array
    {
        return ['commerce-manageCustomers'];
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Repeat Customers', category: 'commerce');
    }

    #[\Override]
    public static function maxColspan(): ?int
    {
        return 1;
    }

    #[\Override]
    public function getTitle(): ?string
    {
        return '';
    }

    #[\Override]
    protected function renderComponent(): ?string
    {
        return 'commerce:widget-number-stat';
    }

    #[\Override]
    protected function renderProps(): array
    {
        return [
            'value' => ($this->stat->get()['percentage'] ?? 0) . '%',
            'label' => t('repeat customers', category: 'commerce'),
            'timeFrame' => $this->stat->getDateRangeWording(),
        ];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make($this->statSettingsFields());
    }
}
