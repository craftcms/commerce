<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\ShippingRulesController;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\Json;
use craft\web\Request;
use craftcommercetests\fixtures\ShippingFixture;
use UnitTester;
use yii\base\InvalidRouteException;

/**
 * ShippingRulesControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.4
 */
class ShippingRulesControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var  ShippingRulesController
     */
    protected ShippingRulesController $controller;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'shipping' => [
                'class' => ShippingFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before(): void
    {
        parent::_before();

        // Mock admin user
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById('1')
        );
        Craft::$app->getUser()->getIdentity()->password = '$2y$13$tAtJfYFSRrnOkIbkruGGEu7TPh0Ixvxq0r.XgWqIgNWuWpxpA7SxK';

        $this->controller = new ShippingRulesController('shippingRules', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testReorder(): void
    {
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $shippingFixture = $this->tester->grabFixture('shipping');

        $ids = [$shippingFixture->data['us-only-2']['id'], $shippingFixture->data['us-only']['id']];
        $this->request->setBodyParams(['ids' => Json::encode($ids)]);

        $response = $this->controller->runAction('reorder');

        self::assertEquals(200, $response->statusCode);
        self::assertIsArray($response->data);
        self::assertEmpty($response->data);

        // Check rules have been reordered
        $results = (new Query())
            ->from(Table::SHIPPINGRULES)
            ->select(['id'])
            ->where(['id' => $ids])
            ->orderBy(['priority' => SORT_ASC])
            ->column();

        self::assertEquals($ids, $results);
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testSave(): void
    {
        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $shippingFixture = $this->tester->grabFixture('shipping');
        $rule = $shippingFixture->data['us-only'];
        $newName = $rule['name'] . ' saved';

        $this->request->setBodyParams([
            'id' => $rule['id'],
            'name' => $newName,
            'methodId' => $rule['methodId'],
            'enabled' => $rule['enabled'],
            'orderConditionFormula' => '',
            'minQty' => 0,
            'maxQty' => 0,
            'minTotal' => 0,
            'maxTotal' => 0,
            'minMaxTotalType' => 'salePrice',
            'minWeight' => 0,
            'maxWeight' => 0,
            'baseRate' => 0,
            'perItemRate' => 0,
            'weightRate' => 0,
            'percentageRate' => 0,
            'minRate' => 0,
            'maxRate' => 0,
            'ruleCategories' => [],
        ]);

        $this->controller->runAction('save');

        // Check rules have been reordered
        $result = (new Query())
            ->from(Table::SHIPPINGRULES)
            ->select(['name'])
            ->where(['id' => $rule['id']])
            ->scalar();

        self::assertEquals($newName, $result);
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testDeleteAjax(): void
    {
        // Test Ajax delete
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $shippingFixture = $this->tester->grabFixture('shipping');

        $this->request->setBodyParams(['id' => $shippingFixture->data['us-only']['id']]);

        $response = $this->controller->runAction('delete');

        self::assertEquals(200, $response->statusCode);
        self::assertIsArray($response->data);
        self::assertEmpty($response->data);
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testDelete(): void
    {
        $originalEdition = Plugin::getInstance()->edition;

        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $shippingFixture = $this->tester->grabFixture('shipping');

        $this->request->setBodyParams(['id' => $shippingFixture->data['us-only-2']['id']]);

        $this->controller->runAction('delete');

        self::assertFalse(false, (new Query())
            ->from(Table::SHIPPINGRULES)
            ->select(['name'])
            ->where(['id' => $shippingFixture->data['us-only-2']['id']])
            ->exists());

        Plugin::getInstance()->edition = $originalEdition;
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testDuplicate(): void
    {
        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $shippingFixture = $this->tester->grabFixture('shipping');
        $rule = $shippingFixture->data['us-only'];

        $this->request->setBodyParams([
            'id' => $rule['id'],
            'name' => $rule['name'],
            'methodId' => $rule['methodId'],
            'enabled' => $rule['enabled'],
            'orderConditionFormula' => '',
            'minQty' => 0,
            'maxQty' => 0,
            'minTotal' => 0,
            'maxTotal' => 0,
            'minMaxTotalType' => 'salePrice',
            'minWeight' => 0,
            'maxWeight' => 0,
            'baseRate' => 0,
            'perItemRate' => 0,
            'weightRate' => 0,
            'percentageRate' => 0,
            'minRate' => 0,
            'maxRate' => 0,
            'ruleCategories' => [],
        ]);

        $this->controller->runAction('duplicate');

        // Check rules have been reordered
        $result = (new Query())
            ->from(Table::SHIPPINGRULES)
            ->select(['id'])
            ->where(['name' => $rule['name']])
            ->count();

        self::assertEquals(2, $result);
    }
}
