<?php

namespace Craft;

/**
 * Class Market_InstallService
 *
 * @package Craft
 */
class Market_InstallService extends BaseApplicationComponent
{

    public function install()
    {

        $this->_createTables();
        $this->_addForeignKeys();
    }

    private function _createTables()
    {
    }

    private function _addForeignKeys()
    {
    }
}