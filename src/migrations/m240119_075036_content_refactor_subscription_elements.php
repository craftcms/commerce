<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Subscription;
use craft\db\Query;
use craft\migrations\BaseContentRefactorMigration;

/**
 * m240119_075036_content_refactor_subscription_elements migration.
 */
class m240119_075036_content_refactor_subscription_elements extends BaseContentRefactorMigration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Migrate Subscription elements
        $this->updateElements(
            (new Query())->from(Table::SUBSCRIPTIONS),
            Craft::$app->getFields()->getLayoutByType(Subscription::class)
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240119_073924_content_refactor_elements cannot be reverted.\n";
        return false;
    }
}
