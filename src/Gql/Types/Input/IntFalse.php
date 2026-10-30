<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Gql\Types\Input;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ScalarType;

class IntFalse extends ScalarType
{
    public string $name = 'IntFalse';

    public ?string $description =
        'The `IntFalse` scalar type represents non-fractional signed whole numeric
values. Int can represent values between -(2^31) and 2^31 - 1 Or `false`';

    private IntType $_intType;

    public function __construct(array $config = [])
    {
        $this->_intType = new IntType();

        parent::__construct($config);
    }

    /**
     * Returns a singleton instance to ensure one type per schema.
     */
    public static function getType(): IntFalse
    {
        return GqlEntityRegistry::getOrCreate(static::getName(), fn() => new self());
    }

    public static function getName(): string
    {
        return 'IntFalse';
    }

    public function serialize($value)
    {
        if (is_bool($value) && $value === false) {
            return false;
        }

        return $this->_intType->serialize($value);
    }

    public function parseValue($value): int|false
    {
        if (is_bool($value) && $value === false) {
            return false;
        }

        return $this->_intType->parseValue($value);
    }

    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof BooleanValueNode) {
            $val = $valueNode->value;
            if ($val === false) {
                return false;
            }

            throw new Error();
        }

        return $this->_intType->parseLiteral($valueNode, $variables);
    }
}
