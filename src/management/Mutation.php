<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use RuntimeException;

/** Serializes inventory, order and payment writes using Craft's configured mutex. */
class Mutation
{
    /** @param string|list<string> $keys */
    public static function run(string|array $keys, callable $operation): mixed
    {
        $mutex = Craft::$app->getMutex();
        $keys = array_values(array_unique((array)$keys));
        sort($keys, SORT_STRING);
        $acquired = [];
        try {
            foreach ($keys as $key) {
                if (!$mutex->acquire($key, 5)) {
                    throw new RuntimeException('Another Commerce operation is in progress.');
                }
                $acquired[] = $key;
            }
            return $operation();
        } finally {
            foreach (array_reverse($acquired) as $key) {
                $mutex->release($key);
            }
        }
    }
}
