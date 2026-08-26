<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Fields;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Field\Field;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\User\Elements\User;
use GraphQL\Type\Definition\Type;

use function CraftCms\Cms\t;

/**
 * Read-only field exposing {@see User::getPrimaryShippingAddressId()} through the normal
 * field-layout serialization pipeline (`toArray()`/GraphQL), which macro-based attributes don't
 * flow through on their own. Not stored — {@see self::dbType()} returns `null`, so
 * {@see self::normalizeValue()} recomputes from the owning element on every access rather than
 * reading persisted content.
 */
class PrimaryShippingAddressIdField extends Field
{
    #[\Override]
    public static function displayName(): string
    {
        return t('Primary Shipping Address ID', category: 'commerce');
    }

    #[\Override]
    public static function icon(): string
    {
        return 'hashtag';
    }

    #[\Override]
    public static function isRequirable(): bool
    {
        return false;
    }

    #[\Override]
    public static function phpType(): string
    {
        return 'int|null';
    }

    #[\Override]
    public static function dbType(): array|string|null
    {
        return null;
    }

    #[\Override]
    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (!$element instanceof User) {
            return null;
        }

        /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        return $element->getPrimaryShippingAddressId();
    }

    #[\Override]
    public function copyValue(ElementInterface $from, ElementInterface $to): void
    {
        // Nothing to copy — the value is always recomputed from the element.
    }

    #[\Override]
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        return Html::tag('div', $value ?? t('None', category: 'commerce'), [
            'class' => 'static-value',
        ]);
    }

    #[\Override]
    public function getContentGqlType(): Type|array
    {
        return Type::int();
    }
}
