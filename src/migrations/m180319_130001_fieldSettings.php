<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\fields\Products;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m180319_130001_fieldSettings migration.
 */
class m180319_130001_fieldSettings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $fields = (new Query())
            ->select(['id', 'type', 'translationMethod', 'settings'])
            ->from(['{{%fields}}'])
            ->where([
                'type' => [
                    Products::class,
                ]
            ])
            ->all($this->db);

        foreach ($fields as $field) {

            $settings = Json::decodeIfJson($field['settings']);

            if (!is_array($settings)) {
                echo 'Field ' . $field['id'] . ' (' . $field['type'] . ') settings were invalid JSON: ' . $field['settings'] . "\n";
                $settings = [];
            }

            $localized = ($field['translationMethod'] === 'site');

            // Exception: Cannot use a scalar value as an array
            $settings['localizeRelations'] = $localized;

            // targetLocale => targetSiteId
            if (!empty($settings['targetLocale'])) {
                $site = Craft::$app->getSites()->getSiteByHandle($settings['targetLocale']);

                if ($site) {
                    $settings['targetSiteId'] = $site->id;
                } else {
                    $settings['targetSiteId'] = Craft::$app->getSites()->getPrimarySite()->id;
                }
            }
            unset($settings['targetLocale']);

            $this->update(
                '{{%fields}}',
                [
                    'translationMethod' => 'none',
                    'settings' => Json::encode($settings),
                ],
                ['id' => $field['id']],
                [],
                false);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180319_130001_fieldSettings cannot be reverted.\n";
        return false;
    }
}
