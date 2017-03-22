<?php
namespace craft\commerce;

use craft\commerce\plugin\Routes;
use craft\commerce\plugin\Services as CommerceServices;

class Plugin extends \craft\base\Plugin
{
    // Traits
    // =========================================================================

    use CommerceServices;
    use Routes;

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

    /**
     * Initialize the plugin.
     */
    private function _init()
    {
        $this->_setPluginComponents();
        $this->_registerCpRoutes();

        // Fire an 'afterInit' event
        $this->trigger(Plugin::EVENT_AFTER_INIT);
    }

}