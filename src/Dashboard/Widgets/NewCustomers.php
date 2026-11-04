<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\NewCustomers as NewCustomersStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class NewCustomers extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    private NewCustomersStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->stat = new NewCustomersStat(
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
    public static function isSelectable(): bool
    {
        return currentUser()?->can('commerce-manageCustomers') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('New Customers', category: 'commerce');
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
    public function component(): ?string
    {
        return 'commerce:widget-number-stat';
    }

    #[\Override]
    public function props(): array
    {
        return [
            'value' => I18N::getFormatter()->asInteger($this->stat->get() ?: 0),
            'label' => t('new customers', category: 'commerce'),
            'timeFrame' => $this->stat->getDateRangeWording(),
        ];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make($this->statSettingsFields());
    }
}
