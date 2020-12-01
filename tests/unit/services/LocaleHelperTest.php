<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Locale;
use craft\commerce\models\Pdf;
use craft\commerce\records\Pdf as PdfRecord;
use UnitTester;
use yii\base\InvalidArgumentException;

/**
 * LocaleHelperTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class LocaleHelperTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;
    
    public function testGetRenderLanguageException()
    {
        $this->tester->expectThrowable(InvalidArgumentException::class, function() {
            $pdf = new Pdf();
            $pdf->language = PdfRecord::LOCALE_ORDER_LANGUAGE;
            $pdf->getRenderLanguage();
        });
    }
    
    public function testSwitchLanguage()
    {
        Locale::switchAppLanguage('nl');
        
        self::assertEquals('nl', Craft::$app->language);
    }
}
