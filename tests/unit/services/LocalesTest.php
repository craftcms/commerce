<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\services\Locales;
use craft\models\Site;
use UnitTester;

/**
 * LocalesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class LocalesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Locales $locales
     */
    protected $locales;    
    

    public function testSetLocale()
    {
        $order = $this->make(Order::class, ['getLanguage' => 'en-GB']);
        
        $this->tester->mockCraftMethods('sites', [
            'getSiteById' => function () {
                $site = new Site();
                $site->language = 'fi';
                return $site;
            }
        ]);
        
        $this->locales->setOrderLocale($order, 'localeCreated');
        
        $this->assertEquals('en-GB', Craft::$app->language);        
        
        $this->locales->setOrderLocale($order, 4);
        
        $this->assertEquals('fi', Craft::$app->language);

        Craft::$app->language = 'nl';

        $this->tester->mockCraftMethods('sites', [
            'getSiteById' => null
        ]);
  
        $this->locales->setOrderLocale($order, 4);

        $this->assertEquals('nl', Craft::$app->language);
    }

    protected function _before()
    {
        parent::_before();

        $this->locales = Plugin::getInstance()->getLocales();
    }
}
