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
use craft\commerce\models\Email;
use craft\commerce\models\Pdf;
use craft\commerce\records\Email as EmailRecord;
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
    
    public function testPdfGetRenderLanguageException()
    {
        $this->tester->expectThrowable(InvalidArgumentException::class, function() {
            $pdf = new Pdf();
            $pdf->language = PdfRecord::LOCALE_ORDER_LANGUAGE;
            $pdf->getRenderLanguage();
        });
    }
    
    public function testPdfGetOrderLanguage()
    {
        $order = new Order();
        $order->orderLanguage = 'nl';
        
        $pdf = new Pdf();
        $pdf->language = PdfRecord::LOCALE_ORDER_LANGUAGE;
        
        $language = $pdf->getRenderLanguage($order);
        
        self::assertEquals('nl', $language);        
        
        $pdf = new Pdf();
        $pdf->language = 'ph';
        
        $language = $pdf->getRenderLanguage($order);
        
        self::assertEquals('ph', $language);
    }
    
    public function testEmailGetRenderLanguageException()
    {
        $this->tester->expectThrowable(InvalidArgumentException::class, function() {
            $email = new Email();
            $email->language = EmailRecord::LOCALE_ORDER_LANGUAGE;
            $email->getRenderLanguage();
        });
    }
    
    public function testEmailGetOrderLanguage()
    {
        $order = new Order();
        $order->orderLanguage = 'nl';
        
        $email = new Email();
        $email->language = EmailRecord::LOCALE_ORDER_LANGUAGE;
        
        $language = $email->getRenderLanguage($order);
        
        self::assertEquals('nl', $language);        
        
        $pdf = new Email();
        $email->language = 'ph';
        
        $language = $email->getRenderLanguage($order);
        
        self::assertEquals('ph', $language);
    }
    
    public function testSwitchLanguage()
    {
        Locale::switchAppLanguage('nl');
        
        self::assertEquals('nl', Craft::$app->language);
    }
}
