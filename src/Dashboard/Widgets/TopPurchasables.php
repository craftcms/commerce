<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use craft\web\assets\admintable\AdminTableAsset;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\Contracts\StatInterface;
use CraftCms\Commerce\Stats\TopPurchasables as TopPurchasablesStat;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class TopPurchasables extends Widget
{
    use StatWidgetTrait;

    /**
     * Options 'revenue', 'qty'.
     */
    public ?string $type = null;

    /**
     * Options 'description', 'sku'.
     */
    public string $nameField = 'description';

    private TopPurchasablesStat $stat;

    private string $title;

    /** @var array<string, string> */
    private array $typeOptions;

    /** @var array<string, string> */
    private array $nameFieldOptions;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $this->storeId = Cp::requestedSite()->getStore()->id;
        }

        $this->nameFieldOptions = [
            'description' => t('Description', category: 'commerce'),
            'sku' => t('SKU', category: 'commerce'),
        ];

        $this->typeOptions = [
            'qty' => t('Qty', category: 'commerce'),
            'revenue' => t('Revenue', category: 'commerce'),
        ];

        $this->title = match ($this->type) {
            'revenue' => t('Top Purchasables by Revenue', category: 'commerce'),
            'qty' => t('Top Purchasables by Qty Sold', category: 'commerce'),
            default => t('Top Purchasables', category: 'commerce'),
        };

        $this->dateRange = $this->dateRange ?: StatInterface::DATE_RANGE_TODAY;

        $this->stat = new TopPurchasablesStat(
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
        return currentUser()?->can('commerce-manageOrders') ?? false;
    }

    #[\Override]
    public static function displayName(): string
    {
        return t('Top Purchasables', category: 'commerce');
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

        return template('commerce/_components/widgets/purchasables/top/body', [
            'stats' => $stats,
            'type' => $this->type,
            'nameField' => $this->nameField,
            'nameFieldLabel' => $this->nameFieldOptions[$this->nameField] ?? '',
            'typeLabel' => $this->typeOptions[$this->type] ?? '',
            'id' => 'top-purchasables' . Str::random(),
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
            Field::make(t('Name Field', category: 'commerce'))
                ->control(Choice::make('nameField')->value($this->nameField)->options(
                    collect($this->nameFieldOptions)->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values()->all()
                )),
            ...$this->statSettingsFields(),
        ]);
    }
}
