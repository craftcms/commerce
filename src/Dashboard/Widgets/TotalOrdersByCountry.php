<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TotalOrdersByCountry as TotalOrdersByCountryStat;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class TotalOrdersByCountry extends Widget
{
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
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->storeId = Cp::requestedSite()->getStore()->id;
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
    public static function icon(): ?string
    {
        return \Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    #[\Override]
    public function getBodyHtml(): ?string
    {
        $stats = $this->stat->get();

        if (empty($stats)) {
            return Html::tag('p', t('No stats available.', category: 'commerce'), ['class' => 'zilch']);
        }

        \Craft::$app->getView()->registerAssetBundle(StatWidgetsAsset::class);

        $id = 'total-orders-by-country' . Str::random();
        $namespaceId = InputNamespace::namespaceId($id);

        $labels = Arr::pluck($stats, 'name');
        $totalOrders = Arr::pluck($stats, 'total');

        return template('commerce/_components/widgets/orders/country/body', compact(
            'stats',
            'namespaceId',
            'labels',
            'totalOrders',
        ), TemplateMode::Cp);
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
            ...$this->statSettingsFields(),
        ]);
    }
}
