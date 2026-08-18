<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use CraftCms\Cms\Support\Arr;
use craft\helpers\Cp;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalRevenue as TotalRevenueStat;
use Illuminate\Validation\Rule;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class TotalRevenue extends Widget
{
    use StatWidgetTrait;

    public string $type = TotalRevenueStat::TYPE_TOTAL;

    public bool $showOrderCount = false;

    private TotalRevenueStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = Cp::requestedSite()->getStore()->id;
        }

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TotalRevenueStat(
            $this->dateRange,
            DateTimeHelper::toDateTime($this->startDate, true),
            DateTimeHelper::toDateTime($this->endDate, true),
            $this->storeId,
        );

        if (!empty($this->orderStatuses)) {
            $this->stat->setOrderStatuses($this->orderStatuses);
        }

        $this->stat->type = $this->type;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'type' => ['required', Rule::in([TotalRevenueStat::TYPE_TOTAL, TotalRevenueStat::TYPE_TOTAL_PAID])],
        ];
    }

    #[\Override]
    public static function isSelectable(): bool
    {
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Total Revenue', category: 'commerce');
    }

    #[\Override]
    public function getTitle(): ?string
    {
        $stats = $this->stat->get();
        $revenue = Arr::pluck($stats, 'revenue');
        $total = round(array_sum($revenue), 0, \RoundingMode::HalfTowardsZero);

        $formattedTotal = Currency::formatAsCurrency($total, $this->getStore()->getCurrency()->getCode(), false, true, true);

        return t('{total} in total revenue', ['total' => $formattedTotal], category: 'commerce');
    }

    #[\Override]
    public function getSubtitle(): ?string
    {
        return $this->stat->getDateRangeWording();
    }

    #[\Override]
    public static function icon(): ?string
    {
        return \Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    #[\Override]
    public function getBodyHtml(): ?string
    {
        $stats = $this->stat->get();
        $timeFrame = $this->stat->getDateRangeWording();
        $chartInterval = $this->stat->getDateRangeInterval();

        \Craft::$app->getView()->registerAssetBundle(StatWidgetsAsset::class);

        $id = 'total-revenue' . Str::random();
        $namespaceId = InputNamespace::namespaceId($id);

        if (empty($stats)) {
            return Html::tag('p', t('No stats available.', category: 'commerce'), ['class' => 'zilch']);
        }

        $labels = Arr::pluck($stats, 'datekey');
        if ($chartInterval == 'month') {
            $labels = array_map(static function($label) {
                [$year, $month] = explode('-', $label);
                $month = $month < 10 ? '0' . $month : $month;
                return implode('-', [$year, $month, '01']);
            }, $labels);
        } elseif ($chartInterval == 'week') {
            $labels = array_map(static function($label) {
                $year = substr($label, 0, 4);
                $week = substr($label, -2);
                return $year . 'W' . $week;
            }, $labels);
        }

        $revenue = Arr::pluck($stats, 'revenue');
        $orderCount = Arr::pluck($stats, 'count');
        $widget = $this;

        return template('commerce/_components/widgets/orders/revenue/body', compact(
            'widget',
            'stats',
            'timeFrame',
            'namespaceId',
            'labels',
            'revenue',
            'orderCount',
            'chartInterval',
        ), TemplateMode::Cp);
    }

    #[\Override]
    public function settingsForm(FormContext $context = new FormContext()): ?Form
    {
        \Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Form::make([
            Field::make(t('Type', category: 'commerce'))
                ->control(Choice::make('type')->value($this->type)->options([
                    ['label' => t('Total', category: 'commerce'), 'value' => TotalRevenueStat::TYPE_TOTAL],
                    ['label' => t('Total Paid', category: 'commerce'), 'value' => TotalRevenueStat::TYPE_TOTAL_PAID],
                ])),
            Field::make(t('Show Order Count?', category: 'commerce'))
                ->control(Lightswitch::make('showOrderCount')->value($this->showOrderCount)),
            ...$this->statSettingsFields(),
        ]);
    }
}
