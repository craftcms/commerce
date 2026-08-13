<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use craft\web\assets\admintable\AdminTableAsset;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TopProducts as TopProductsStat;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class TopProducts extends Widget
{
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
            $this->storeId = Cp::requestedSite()->getStore()->id;
        }

        $this->typeOptions = [
            TopProductsStat::TYPE_QTY => t('Qty', category: 'commerce'),
            TopProductsStat::TYPE_REVENUE => t('Revenue', category: 'commerce'),
        ];

        $this->revenueCheckboxOptions = [
            ['value' => TopProductsStat::REVENUE_OPTION_DISCOUNT, 'label' => t('Discount', category: 'commerce') . ' — ' . t('Include line item discounts.', category: 'commerce')],
            ['value' => TopProductsStat::REVENUE_OPTION_TAX_INCLUDED, 'label' => t('Tax (inc)', category: 'commerce') . ' — ' . t('Include built-in line item tax.', category: 'commerce')],
            ['value' => TopProductsStat::REVENUE_OPTION_TAX, 'label' => t('Tax', category: 'commerce') . ' — ' . t('Include separate line item tax.', category: 'commerce')],
            ['value' => TopProductsStat::REVENUE_OPTION_SHIPPING, 'label' => t('Shipping', category: 'commerce') . ' — ' . t('Include line item shipping costs.', category: 'commerce')],
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
    public static function icon(): ?string
    {
        return \Craft::getAlias('@craft/commerce/icon-mask.svg');
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
    public function getBodyHtml(): ?string
    {
        $stats = $this->stat->get();

        if (empty($stats)) {
            return Html::tag('p', t('No stats available.', category: 'commerce'), ['class' => 'zilch']);
        }

        \Craft::$app->getView()->registerAssetBundle(StatWidgetsAsset::class);
        \Craft::$app->getView()->registerAssetBundle(AdminTableAsset::class);

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

        return template('commerce/_components/widgets/products/top/body', [
            'stats' => $stats,
            'revenueColumnHandle' => $revenueColumnHandle,
            'type' => $this->type,
            'typeLabel' => $this->typeOptions[$this->type] ?? '',
            'id' => 'top-products' . StringHelper::randomString(),
        ], TemplateMode::Cp);
    }

    #[\Override]
    public function settingsForm(FormContext $context = new FormContext()): ?Form
    {
        \Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Form::make([
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
