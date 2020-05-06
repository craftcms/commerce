<?php


namespace craftcommercetests\fixtures;


use crafttests\fixtures\GqlSchemasFixture as CraftGqlSchemasFixture;

class GqlSchemasFixture extends CraftGqlSchemasFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/commerce-gql-schemas.php';
}