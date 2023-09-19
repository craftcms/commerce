<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Order;
use craft\db\Migration;
use craft\services\ElementSources;
use craft\services\ProjectConfig;

/**
 * m230918_134752_reset_order_element_sources migration.
 */
class m230918_134752_reset_order_element_sources extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '5.0.29', '<')) {
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;
            $configSources = $projectConfig->get(ProjectConfig::PATH_ELEMENT_SOURCES . '.' . Order::class);

            if (!empty($configSources)) {
                $customSources = array_filter($configSources, fn($src) => isset($src['type']) && $src['type'] === 'custom');

                if (empty($customSources)) {
                    $projectConfig->remove(ProjectConfig::PATH_ELEMENT_SOURCES . '.' . Order::class);
                } else {
                    $sources = Order::sources(ElementSources::CONTEXT_INDEX);
                    $normalized = [];
                    foreach ($sources as $source) {
                        if (isset($source['type'])) {
                            $normalized[] = $source;
                        } elseif (array_key_exists('heading', $source)) {
                            $source['type'] = ElementSources::TYPE_HEADING;
                            $normalized[] = $source;
                        } elseif (isset($source['key'])) {
                            $source['type'] = ElementSources::TYPE_NATIVE;
                            $normalized[] = $source;
                        }
                    }

                    foreach ($customSources as $customSource) {
                        $normalized[] = $customSource;
                    }
                    $normalized = \craft\helpers\ProjectConfig::cleanupConfig($normalized);
                    $projectConfig->set(ProjectConfig::PATH_ELEMENT_SOURCES . '.' . Order::class, $normalized);
                }
            }

            $projectConfig->muteEvents = $muteEvents;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230918_134752_reset_order_element_sources cannot be reverted.\n";
        return false;
    }
}
