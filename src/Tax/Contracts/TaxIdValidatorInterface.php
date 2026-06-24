<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Contracts;

interface TaxIdValidatorInterface
{
    public static function displayName(): string;

    public function validateFormat(string $idNumber): bool;

    public function validateExistence(string $idNumber): bool;

    public function validate(string $idNumber): bool;

    public static function isEnabled(): bool;
}
