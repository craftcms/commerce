<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use CraftCms\Cms\Support\Arr;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalOrders as TotalOrdersStat;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class TotalOrders extends Widget
{
    use StatWidgetTrait;

    public mixed $showChart = null;

    private TotalOrdersStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = Cp::requestedSite()->getStore()->id;
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
    public static function icon(): ?string
    {
        return \Craft::getAlias('@craft/commerce/icon-mask.svg');
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
    public function getBodyHtml(): ?string
    {
        $showChart = $this->showChart;
        $stats = $this->stat->get();

        if (empty($stats)) {
            return Html::tag('p', t('No stats available.', category: 'commerce'), ['class' => 'zilch']);
        }

        $number = $stats['total'] ?? 0;
        $chart = $stats['chart'] ?? [];

        $labels = Arr::pluck($chart, 'datekey');
        $data = Arr::pluck($chart, 'total');

        $timeFrame = $this->stat->getDateRangeWording();
        $number = I18N::getFormatter()->asInteger($number);

        $id = 'total-orders' . StringHelper::randomString();
        $namespaceId = InputNamespace::namespaceId($id);

        \Craft::$app->getView()->registerAssetBundle(StatWidgetsAsset::class);

        return template('commerce/_components/widgets/orders/total/body', compact(
            'namespaceId',
            'number',
            'timeFrame',
            'labels',
            'data',
            'showChart',
        ), TemplateMode::Cp);
    }

    #[\Override]
    public function settingsForm(FormContext $context = new FormContext()): ?Form
    {
        \Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Form::make([
            Field::make(t('Show Chart?', category: 'commerce'))
                ->control(Lightswitch::make('showChart')->value((bool)$this->showChart)),
            ...$this->statSettingsFields(),
        ]);
    }
}
