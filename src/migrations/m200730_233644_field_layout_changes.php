<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\fieldlayoutelements\ProductTitleField;
use craft\commerce\fieldlayoutelements\VariantsField;
use craft\commerce\fieldlayoutelements\VariantTitleField;
use craft\commerce\Plugin;
use craft\db\Migration;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayoutTab;

/**
 * m200730_233644_field_layout_changes migration.
 */
class m200730_233644_field_layout_changes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn('{{%commerce_producttypes}}', 'titleLabel');
        $this->dropColumn('{{%commerce_producttypes}}', 'variantTitleLabel');

        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '3.2.1', '>=')) {
            return;
        }

        foreach ($projectConfig->get('commerce.productTypes') ?? [] as $typeUid => $typeConfig) {
            if (!empty($typeConfig['productFieldLayouts'])) {
                foreach ($typeConfig['productFieldLayouts'] as $fieldLayoutUid => &$fieldLayoutConfig) {

                    // Field layout could be null if they had no product field layout for products previously and
                    // Craft 3.5 regenerated the project config into folders, creating a null field layout.
                    // So just go ahead and make it an array as it expects, and let the _updateFieldLayoutConfig fix it up.
                    if (!$fieldLayoutConfig) {
                        $fieldLayoutConfig = [];
                    }

                    $this->_updateFieldLayoutConfig($fieldLayoutConfig);

                    // Add the Title field to the first tab
                    array_unshift($fieldLayoutConfig['tabs'][0]['elements'], [
                        'type' => ProductTitleField::class,
                        'label' => $typeConfig['titleLabel'] ?? null,
                    ]);

                    if ($typeConfig['hasVariants'] ?? false) {
                        // Add the Variants tab + field
                        $variantTabName = Plugin::t('Variants');
                        if (ArrayHelper::contains($fieldLayoutConfig['tabs'], 'name', $variantTabName)) {
                            $variantTabName .= ' ' . StringHelper::randomString(10);
                        }
                        if (count($fieldLayoutConfig['tabs']) === 1) {
                            $topSortOrder = $fieldLayoutConfig['tabs'][0]['sortOrder'];
                        } else {
                            $topSortOrder = max(...ArrayHelper::getColumn($fieldLayoutConfig['tabs'], 'sortOrder'));
                        }
                        $fieldLayoutConfig['tabs'][] = [
                            'name' => $variantTabName,
                            'sortOrder' => $topSortOrder + 1,
                            'elements' => [
                                [
                                    'type' => VariantsField::class,
                                ]
                            ],
                        ];
                    }
                }
                unset($fieldLayoutConfig);
            }

            if (!empty($typeConfig['variantFieldLayouts'])) {
                foreach ($typeConfig['variantFieldLayouts'] as $fieldLayoutUid => &$fieldLayoutConfig) {

                    // Field layout could be null if they had no variant field layout for variants previously and
                    // Craft 3.5 regenerated the project config into folders, creating a null field layout.
                    // So just go ahead and make it an array as it expects, and let the _updateFieldLayoutConfig fix it up.
                    if (!$fieldLayoutConfig) {
                        $fieldLayoutConfig = [];
                    }

                    $this->_updateFieldLayoutConfig($fieldLayoutConfig);

                    // Add the Title field to the first tab
                    array_unshift($fieldLayoutConfig['tabs'][0]['elements'], [
                        'type' => VariantTitleField::class,
                        'label' => $typeConfig['variantTitleLabel'] ?? null,
                    ]);
                }
                unset($fieldLayoutConfig);
            }

            unset(
                $typeConfig['titleLabel'],
                $typeConfig['variantTitleLabel']
            );

            $projectConfig->set("commerce.productTypes.$typeUid", $typeConfig);
        }
    }

    /**
     * @param array $fieldLayoutConfig
     */
    private function _updateFieldLayoutConfig(array &$fieldLayoutConfig)
    {
        // Make sure there's at least one tab
        if (empty($fieldLayoutConfig['tabs'])) {
            $fieldLayoutConfig['tabs'] = [[
                'name' => 'Content',
                'sortOrder' => 1,
            ]];
        }

        // Update the tab configs to the new format
        foreach ($fieldLayoutConfig['tabs'] as &$tabConfig) {
            FieldLayoutTab::updateConfig($tabConfig);
        }
        unset($tabConfig);

        // Ensure the first tab has an elements array
        $firstTab = &$fieldLayoutConfig['tabs'][0];
        if (!isset($firstTab['elements'])) {
            $firstTab['elements'] = [];
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200730_233644_field_layout_changes cannot be reverted.\n";
        return false;
    }
}
