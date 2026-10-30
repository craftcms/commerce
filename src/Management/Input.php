<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Management;

use Illuminate\Validation\ValidationException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class Input
{
    /** @param array<string, mixed> $schema */
    public static function validate(array $values, array $schema): void
    {
        $data = json_decode(json_encode($values ?: (object)[], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        $schema = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        $result = new Validator()->validate($data, $schema);

        if ($error = $result->error()) {
            throw ValidationException::withMessages(new ErrorFormatter()->format($error));
        }
    }
}
