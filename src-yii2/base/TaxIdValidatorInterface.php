<?php

namespace craft\commerce\base;

/**
 * @deprecated use {@see \CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface}
 */
interface TaxIdValidatorInterface
{
    public static function displayName(): string;

    public function validateFormat(string $idNumber): bool;

    public function validateExistence(string $idNumber): bool;

    public function validate(string $idNumber): bool;

    public static function isEnabled(): bool;
}
