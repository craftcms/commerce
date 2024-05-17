<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\gql;

use Craft;
use craft\models\GqlSchema;
use craftcommercetests\fixtures\GqlSchemasFixture;
use craftcommercetests\fixtures\ProductFixture;
use FunctionalTester;
use yii\base\Exception;

/**
 * Class GqlCest
 *
 * @since 5.0.7
 */
class GqlCest
{
    /**
     *
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
            'gqlSchemas' => [
                'class' => GqlSchemasFixture::class,
            ],
        ];
    }

    private bool $tokenStatus;

    /**
     * @param FunctionalTester $I
     */
    public function _before(FunctionalTester $I)
    {
        $gql = Craft::$app->getGql();
        $token = $gql->getPublicToken();
        $this->tokenStatus = $token->enabled;
        $token->enabled = false;
        $gql->saveToken($token);

        $this->_setSchema(1000);
    }

    /**
     * @param FunctionalTester $I
     */
    public function _after(FunctionalTester $I)
    {
        $gql = Craft::$app->getGql();
        $token = $gql->getPublicToken();
        $token->enabled = $this->tokenStatus;
        $gql->saveToken($token);

        $gql->flushCaches();
    }

    /**
     * @param int $schemaId
     * @return GqlSchema|null
     * @throws Exception
     */
    public function _setSchema(int $schemaId): ?GqlSchema
    {
        $gqlService = Craft::$app->getGql();
        $schema = $gqlService->getSchemaById($schemaId);
        $gqlService->setActiveSchema($schema);

        return $schema;
    }

    /**
     * Test whether all query types work correctly
     */
    public function testQuerying(FunctionalTester $I): void
    {
        $queryTypes = [
            'products',
            'variants',
        ];

        foreach ($queryTypes as $queryType) {
            $I->amOnPage('?action=graphql/api&query={' . $queryType . '{title}}');
            $I->see('"' . $queryType . '":[');
        }
    }

    /**
     * Test whether querying for wrong gql field returns the correct error.
     */
    public function testWrongGqlField(FunctionalTester $I): void
    {
        $parameter = 'bogus';
        $I->amOnPage('?action=graphql/api&query={products{' . $parameter . '}}');
        $I->see('"Cannot query field \"' . $parameter . '\"');
    }

    /**
     * Test whether querying with wrong parameters returns the correct error.
     */
    public function testWrongGqlQueryParameter(FunctionalTester $I): void
    {
        $I->amOnPage('?action=graphql/api&query={products(limit:[5,2]){title}}');
        $I->see('requires type Int');
    }

    /**
     * Test whether query results yield the expected results.
     */
    public function testQueryResults(FunctionalTester $I): void
    {
        $testData = file_get_contents(__DIR__ . '/data/gql.txt');
        foreach (explode('-----TEST DELIMITER-----', $testData) as $case) {
            [$query, $response] = explode('-----RESPONSE DELIMITER-----', $case);
            [$schemaId, $query] = explode('-----TOKEN DELIMITER-----', $query);
            $this->_setSchema((int)trim($schemaId));
            $I->amOnPage('?action=graphql/api&query=' . urlencode(trim($query)));
            $I->see(trim($response));
            $gqlService = Craft::$app->getGql();
            $gqlService->flushCaches();
        }
    }
}
