<?php

declare(strict_types=1);

namespace craft\commerce\mcp;

use Craft;
use InvalidArgumentException;
use Mcp\Exception\ToolCallException;
use Throwable;
use yii\web\ForbiddenHttpException;

trait CallsCommerce
{
    private function call(callable $operation): array
    {
        try {
            return $operation();
        } catch (ForbiddenHttpException $exception) {
            throw new ToolCallException('Resource not found.', previous: $exception);
        } catch (InvalidArgumentException $exception) {
            throw new ToolCallException($exception->getMessage(), previous: $exception);
        } catch (ToolCallException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Craft::error($exception, __METHOD__);
            throw new ToolCallException('Commerce could not complete the operation. Check the server logs before retrying a write.', previous: $exception);
        }
    }
}
