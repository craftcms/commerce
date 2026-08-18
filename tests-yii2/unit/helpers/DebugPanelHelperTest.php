<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\helpers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\debug\CommercePanel;
use craft\commerce\events\CommerceDebugPanelDataEvent;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\Discount;
use craft\commerce\models\Sale;
use craft\helpers\Html;
use craft\services\Users;
use UnitTester;
use yii\helpers\VarDumper;

/**
 * LocaleHelperTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class DebugPanelHelperTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @param array $models
     * @param array|null $names
     * @param array|null $prepend
     * @param array $expected
     * @throws \yii\base\InvalidConfigException
     * @dataProvider prependOrAppendModelTabDataProvider
     */
    public function testPrependOrAppendModelTab(array $models, ?array $names, ?array $prepend, array $expected): void
    {
        Craft::$app->getConfig()->getGeneral()->devMode = true;
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById('1')
        );

        $usersServices = $this->make(Users::class, [
            'getUserPreferences' => fn($userId) => [
                'enableDebugToolbarForSite' => true,
                'enableDebugToolbarForCp' => true,
            ],
        ]);
        Craft::$app->set('users', $usersServices);

        foreach ($models as $key => $model) {
            DebugPanel::prependOrAppendModelTab($model, $names[$key], $prepend[$key]);
        }

        $event = new CommerceDebugPanelDataEvent(['nav' => [], 'content' => []]);
        $commercePanel = new CommercePanel();
        $commercePanel->trigger(CommercePanel::EVENT_AFTER_DATA_PREPARE, $event);

        foreach ($models as $key => $model) {
            self::assertIsArray($event->nav);
            self::assertIsArray($event->content);
            self::assertContains($expected[$key]['name'], $event->nav);
            self::assertStringContainsString($expected[$key]['content'], $event->content[$expected[$key]['position']]);
        }
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function prependOrAppendModelTabDataProvider(): array
    {
        $discount = new Discount();
        $discount->id = 1;

        $sale = new Sale();
        $sale->id = 123;
        return [
            [
                [
                    $discount,
                ],
                [
                    null,
                ],
                [
                    true,
                ],
                [
                    [
                        'name' => 'Discount (ID: 1)',
                        'content' => '<tr><th>id</th><td><code>1</code></td></tr>',
                        'position' => 0,
                    ],
                ],
            ],
            [
                [
                    $sale,
                    $discount,
                ],
                [
                    'Test Custom Name',
                    null,
                ],
                [
                    false,
                    true,
                ],
                [
                    [
                        'name' => 'Test Custom Name',
                        'content' => '<tr><th>id</th><td><code>123</code></td></tr>',
                        'position' => 1,
                    ],
                    [
                        'name' => 'Discount (ID: 1)',
                        'content' => '<tr><th>id</th><td><code>1</code></td></tr>',
                        'position' => 0,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $attr
     * @param string|null $label
     * @param string $expected
     * @return void
     * @dataProvider renderModelAttributeRowDataProvider
     */
    public function testRenderModelAttributeRow(string $attr, mixed $value, ?string $label = null, string $expected = ''): void
    {
        self::assertEquals($expected, DebugPanel::renderModelAttributeRow($attr, $value, $label));
    }

    public function renderModelAttributeRowDataProvider(): array
    {
        $discountVarDump = VarDumper::dumpAsString(new Discount());
        return [
            [
                'stringAttr',
                'Test string',
                null,
                '<tr><th>stringAttr</th><td><code>Test string</code></td></tr>',
            ],
            [
                'stringAttr',
                'Custom label',
                'Customize the label',
                '<tr><th>Customize the label</th><td><code>Custom label</code></td></tr>',
            ],
            [
                'modelAttr',
                $discountVarDump,
                null,
                '<tr><th>modelAttr</th><td><code>' . $discountVarDump . '</code></td></tr>',
            ],
            [
                'attrHtml',
                '<span class="foo">Extra & useful HTML</span>',
                null,
                '<tr><th>attrHtml</th><td><code>' . Html::encode('<span class="foo">Extra & useful HTML</span>') . '</code></td></tr>',
            ],
        ];
    }
}
