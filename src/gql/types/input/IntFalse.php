<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\input;

use craft\gql\GqlEntityRegistry;
use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ScalarType;

/**
 * Class IntFalse
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.7
 */
class IntFalse extends ScalarType
{
    public $name = 'IntFalse';

    /** @var string */
    public $description =
        'The `IntFalse` scalar type represents non-fractional signed whole numeric
values. Int can represent values between -(2^31) and 2^31 - 1 Or `false`';

    /**
     * @var IntType|null
     */
    private ?IntType $_intType = null;

    public function __construct(array $config = [])
    {
        $this->_intType = new IntType();

        parent::__construct($config);
    }

    /**
     * Returns a singleton instance to ensure one type per schema.
     *
     * @return IntFalse
     */
    public static function getType(): IntFalse
    {
        return GqlEntityRegistry::getOrCreate(static::getName(), fn() => new self());
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'IntFalse';
    }

    /**
     * @param $value
     * @return false|int|mixed|null
     * @throws Error
     */
    public function serialize($value)
    {
        if (is_bool($value) && $value === false) {
            return false;
        }

        // If it isn't `false` use the `IntType` to serialize the value
        return $this->_intType->serialize($value);
    }

    /**
     * @param $value
     * @return int|false
     * @throws Error
     */
    public function parseValue($value): int|false
    {
        if (is_bool($value) && $value === false) {
            return false;
        }

        return $this->_intType->parseValue($value);
    }

    /**
     * @param $valueNode
     * @param array|null $variables
     * @return false|int|mixed
     * @throws Error
     */
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
