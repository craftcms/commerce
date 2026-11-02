<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use RuntimeException;

/** Serializes inventory, order and payment writes using Craft's configured mutex. */
class Mutation
{
    public static function run(string $key, callable $operation): mixed
    {
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire($key, 5)) {
            throw new RuntimeException('Another Commerce operation is in progress.');
        }
        try {
            return $operation();
        } finally {
            $mutex->release($key);
        }
    }
}
