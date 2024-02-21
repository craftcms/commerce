<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Transfer;
use craft\commerce\fieldlayoutelements\TransferManagementField;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use yii\base\Component;

/**
 * Transfers service
 */
class Transfers extends Component
{
    public const CONFIG_FIELDLAYOUT_KEY = 'commerce.transfers.fieldLayouts';

    /**
     * Handle field layout change
     *
     * @throws Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Order::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Order::class)->id;
        $layout->type = Transfer::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Transfer::class);
    }



    /**
     * @return FieldLayout
     */
    public function getFieldLayout(): FieldLayout
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Transfer::class);

        if (!$fieldLayout->isFieldIncluded('locations')) {
            $layoutTabs = $fieldLayout->getTabs();
            $TransfersTabName = Craft::t('commerce', 'Locations');
            if (ArrayHelper::contains($layoutTabs, 'name', $TransfersTabName)) {
                $TransfersTabName .= ' ' . StringHelper::randomString(10);
            }

            $contentTab = new FieldLayoutTab();
            $contentTab->setLayout($fieldLayout);
            $contentTab->name = $TransfersTabName;
            $contentTab->setElements([
                ['type' => TransferManagementField::class],
            ]);

            $layoutTabs[] = $contentTab;
            $fieldLayout->setTabs($layoutTabs);
        }

        return $fieldLayout;
    }
}
