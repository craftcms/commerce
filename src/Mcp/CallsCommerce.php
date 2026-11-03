<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mcp\Exception\ToolCallException;
use Throwable;

trait CallsCommerce
{
    private function call(callable $operation): array
    {
        try {
            return $operation();
        } catch (AuthorizationException $exception) {
            throw new ToolCallException('Resource not found.', previous: $exception);
        } catch (ValidationException $exception) {
            throw new ToolCallException(implode("\n", array_merge(...array_values($exception->errors()))), previous: $exception);
        } catch (InvalidArgumentException $exception) {
            throw new ToolCallException($exception->getMessage(), previous: $exception);
        } catch (ToolCallException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new ToolCallException('Commerce could not complete the operation. Check the server logs before retrying a write.', previous: $exception);
        }
    }
}
