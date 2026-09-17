<?php

declare(strict_types=1);

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Gql;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Support\Facades\Gql as GqlFacade;

/**
 * Executes a query against the currently active schema (see gqlActivateFullAccessSchema()/
 * gqlActivateSchema()) and returns the raw ['data' => ..., 'errors' => ...] result array.
 *
 * Goes straight through Gql::executeQuery() rather than a real HTTP request to the `graphql/api`
 * action — this Testbench harness boots Craft as a package dependency with Commerce installed on
 * top of a skeleton app, and a real routed request's site resolution doesn't hold up in that setup
 * the way it does in a full Craft application. craftcms/yii2-adapter's own Laravel test suite for
 * legacy GQL behavior takes the same direct-execution approach for this reason.
 */
function graphQL(string $query): array
{
    // debugMode: true restores the `FieldsOnCorrectType`/`KnownTypeNames` validation rules that
    // Gql::getValidationRules() otherwise skips outside debug mode (a documented perf trade-off,
    // since generating their suggestion messages requires building the full schema) - without it,
    // an unknown field name is silently dropped instead of producing a GraphQL error.
    return GqlFacade::executeQuery(GqlFacade::getActiveSchema(), $query, debugMode: true);
}

function gqlActivateFullAccessSchema(?string $name = null): void
{
    app(Gql::class)->flushCaches();

    GqlFacade::setActiveSchema(new GqlSchema([
        'name' => $name ?? 'GraphQL ' . bin2hex(random_bytes(4)),
        'scope' => GqlHelper::createFullAccessSchema()->scope,
    ]));
}

function gqlActivateSchema(array $scope, ?string $name = null): GqlSchema
{
    app(Gql::class)->flushCaches();

    $schema = new GqlSchema([
        'name' => $name ?? 'GraphQL ' . bin2hex(random_bytes(4)),
        'scope' => $scope,
    ]);
    GqlFacade::setActiveSchema($schema);

    return $schema;
}
