<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\View\LegacyAssets\InternalAssetRegistry;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Assets\StatWidgetsAsset;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\HasCommerceIcon;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalOrders as TotalOrdersStat;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class TotalOrders extends Widget
{
    use HasCommerceIcon;
    use StatWidgetTrait;

    public mixed $showChart = null;

    private TotalOrdersStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->storeId = app(RequestedSite::class)->get()?->getStore()->id ?? app(Stores::class)->getPrimaryStore()->id;
        }

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TotalOrdersStat(
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
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Total Orders', category: 'commerce');
    }

    #[\Override]
    public static function maxColspan(): ?int
    {
        return 1;
    }

    #[\Override]
    public function getTitle(): ?string
    {
        if (!$this->showChart) {
            return '';
        }

        $stats = $this->stat->get();
        $total = $stats['total'] ?? 0;
        $total = I18N::getFormatter()->asInteger($total);

        return t('{total} orders', ['total' => $total], category: 'commerce');
    }

    #[\Override]
    public function getSubtitle(): ?string
    {
        if (!$this->showChart) {
            return '';
        }

        return $this->stat->getDateRangeWording();
    }

    #[\Override]
    public function props(): array
    {
        $showChart = $this->showChart;
        $stats = $this->stat->get();

        if (empty($stats)) {
            return ['html' => Html::tag('p', t('No stats available.', category: 'commerce'), ['class' => 'zilch'])];
        }

        $number = $stats['total'] ?? 0;
        $chart = $stats['chart'] ?? [];

        $labels = Arr::pluck($chart, 'datekey');
        $data = Arr::pluck($chart, 'total');

        $timeFrame = $this->stat->getDateRangeWording();
        $number = I18N::getFormatter()->asInteger($number);

        $id = 'total-orders' . Str::random();
        $namespaceId = InputNamespace::namespaceId($id);

        app(InternalAssetRegistry::class)->register(StatWidgetsAsset::class);

        return ['html' => template('commerce/_components/widgets/orders/total/body', compact(
            'namespaceId',
            'number',
            'timeFrame',
            'labels',
            'data',
            'showChart',
        ), TemplateMode::Cp)];
    }

    #[\Override]
    public function settingsUi(UiContext $context = new UiContext()): ?Ui
    {
        return Ui::make([
            Field::make(t('Show Chart?', category: 'commerce'))
                ->control(Lightswitch::make('showChart')->value((bool)$this->showChart)),
            ...$this->statSettingsFields(),
        ]);
    }
}
