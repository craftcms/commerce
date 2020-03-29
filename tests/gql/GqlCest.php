<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\gql;

use Craft;
use craftcommercetests\fixtures\GqlSchemasFixture;
use craftcommercetests\fixtures\ProductFixture;
use FunctionalTester;

class GqlCest
{
    public function _fixtures()
    {
        return [
            'products' => [
                'class' => ProductFixture::class
            ],
            'gqlSchemas' => [
                'class' => GqlSchemasFixture::class
            ],
        ];
    }

    public function _before(FunctionalTester $I)
    {
        $this->_setSchema(1000);
    }

    public function _after(FunctionalTester $I)
    {
        $gqlService = Craft::$app->getGql();
        $gqlService->flushCaches();
    }

    public function _setSchema(int $tokenId)
    {
        $gqlService = Craft::$app->getGql();
        $schema = $gqlService->getSchemaById($tokenId);
        $gqlService->setActiveSchema($schema);

        return $schema;
    }

    /**
     * Test whether all query types work correctly
     */
    public function testQuerying(FunctionalTester $I)
    {
        $queryTypes = [
            'products',
        ];

        foreach ($queryTypes as $queryType) {
            $I->amOnPage('?action=graphql/api&query={' . $queryType . '{title}}');
            $I->see('"' . $queryType . '":[');
        }
    }

    /**
     * Test whether querying for wrong gql field returns the correct error.
     */
    public function testWrongGqlField(FunctionalTester $I)
    {
        $parameter = 'bogus';
        $queryTypes = [
            'products',
        ];

        foreach ($queryTypes as $queryType) {
          $I->amOnPage('?action=graphql/api&query={' . $queryType . '{' . $parameter . '}}');
          $I->see('"Cannot query field \"' . $parameter . '\"');
        }
    }

    /**
     * Test whether query results yield the expected results.
     */
    public function testQueryResults(FunctionalTester $I)
    {
        $testData = file_get_contents(__DIR__ . '/data/gql.txt');
        foreach (explode('-----TEST DELIMITER-----', $testData) as $case) {
            list ($query, $response) = explode('-----RESPONSE DELIMITER-----', $case);
            list ($schemaId, $query) = explode('-----TOKEN DELIMITER-----', $query);
            $schema = $this->_setSchema(trim($schemaId));
            $I->amOnPage('?action=graphql/api&query='.urlencode(trim($query)));
            $I->see(trim($response));
            $gqlService = Craft::$app->getGql();
            $gqlService->flushCaches();
        }
    }
}
