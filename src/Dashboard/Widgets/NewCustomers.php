<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets;

use craft\commerce\web\assets\commercewidgets\CommerceWidgetsAsset;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\Cp;
use CraftCms\Cms\Dashboard\Widgets\Widget;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Dashboard\Widgets\Concerns\StatWidgetTrait;
use CraftCms\Commerce\Stats\NewCustomers as NewCustomersStat;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class NewCustomers extends Widget
{
    use StatWidgetTrait;

    private NewCustomersStat $stat;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (!$this->storeId) {
            $this->storeId = Cp::requestedSite()->getStore()->id;
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
        return '';
    }

    #[\Override]
    public function getBodyHtml(): ?string
    {
        $number = $this->stat->get();
        $timeFrame = $this->stat->getDateRangeWording();

        \Craft::$app->getView()->registerAssetBundle(StatWidgetsAsset::class);

        return template('commerce/_components/widgets/customers/new/body', compact('number', 'timeFrame'), TemplateMode::Cp);
    }

    #[\Override]
    public function settingsForm(FormContext $context = new FormContext): ?Form
    {
        \Craft::$app->getView()->registerAssetBundle(CommerceWidgetsAsset::class);

        return Form::make($this->statSettingsFields());
    }
}
