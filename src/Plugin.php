<?php
namespace craft\commerce;

use craft\commerce\base\PluginTrait;

class Plugin extends \craft\base\Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;

    // Constants
    // =========================================================================

    /**
     * @event \yii\base\Event The event that is triggered after the plugin has been initialized
     */
    const EVENT_AFTER_INIT = 'afterInit';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->_init();
    }
}