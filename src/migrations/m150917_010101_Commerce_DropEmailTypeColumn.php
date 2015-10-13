<?php
namespace Craft;

class m150917_010101_Commerce_DropEmailTypeColumn extends BaseMigration
{
    public function safeUp()
    {
        $this->dropColumn('commerce_emails', 'type');
        return true;
    }
}