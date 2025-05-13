<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\elements\User;

/**
 * m240529_095819_remove_commerce_user_field migration.
 */
class m240529_095819_remove_commerce_user_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $fieldClassName = 'craft\\commerce\\fieldlayoutelements\\UserCommerceField';

        // Get the field layout used by the user element
        $fieldLayout = Craft::$app->fields->getLayoutByType(\craft\elements\User::class);

        $tabs = $fieldLayout->getTabs();

        foreach ($tabs as $tab) {
            $newFields = [];

            foreach ($tab->elements as $element) {
                if (get_class($element) !== $fieldClassName) {
                    $newFields[] = $element;
                }
            }

            $tab->setElements($newFields);
        }

        // Save the modified field layout
        Craft::$app->fields->saveLayout($fieldLayout);


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240529_095819_remove_commerce_user_field cannot be reverted.\n";
        return false;
    }
}
