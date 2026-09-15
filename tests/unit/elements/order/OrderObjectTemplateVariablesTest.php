<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use DateTime;
use UnitTester;

/**
 * OrderObjectTemplateVariablesTest
 *
 * @see https://github.com/craftcms/commerce/issues/4255
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6
 */
class OrderObjectTemplateVariablesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * `craft\web\View::renderObjectTemplate()` now populates its template variables from `fields()` before
     * falling back to an object's raw attributes. `Order::fields()` re-serializes datetime attributes into
     * `['date' => ..., 'time' => ...]` arrays for the control panel's Vue components, so without passing the
     * raw values in via `Order::getObjectTemplateVariables()`, filtering `dateOrdered` through the `date` Twig
     * filter in an object template (e.g. a PDF file name format) would throw an "Array to string conversion"
     * exception.
     */
    public function testDateFilterOnDatetimeAttribute(): void
    {
        $order = new Order();
        $order->dateOrdered = new DateTime('2026-03-16 12:16:00');

        $fileName = \Craft::$app->getView()->renderSandboxedObjectTemplate(
            'Invoice-{{ dateOrdered|date(\'Y-m-d\') }}',
            $order,
            $order->getObjectTemplateVariables(),
        );

        self::assertSame('Invoice-2026-03-16', $fileName);
    }
}
