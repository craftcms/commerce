<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m170616_154500_productTypeSites_upgrade migration.
 */
class m170616_154500_productTypeSites_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_producttypes_i18n}}', 'template', $this->string(500));
        $this->addColumn('{{%commerce_producttypes_i18n}}', 'hasUrls', $this->boolean());

        // Migrate hasUrls to be site specific
        $productTypes = (new Query())
            ->select(['id', 'hasUrls', 'template'])
            ->from(['{{%commerce_producttypes}}'])
            ->all();

        foreach ($productTypes as $productType) {

            $productTypeSites = (new Query())
                ->select('*')
                ->from(['{{%commerce_producttypes_i18n}}'])
                ->all();

            foreach ($productTypeSites as $productTypeSite) {
                $productTypeSite['template'] = $productType['template'];
                $productTypeSite['hasUrls'] = $productType['hasUrls'];
                $this->update('{{%commerce_producttypes_i18n}}', $productTypeSite, ['id' => $productTypeSite['id']]);
            }
        }

        $this->dropColumn('{{%commerce_producttypes}}', 'template');
        $this->dropColumn('{{%commerce_producttypes}}', 'hasUrls');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170616_154500_productTypeSites_upgrade cannot be reverted.\n";

        return false;
    }
}
