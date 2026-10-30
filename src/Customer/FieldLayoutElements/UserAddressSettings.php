<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\FieldLayoutElements;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseField;
use CraftCms\Cms\User\Elements\User;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class UserAddressSettings extends BaseField
{
    #[Override]
    public function attribute(): string
    {
        return 'commerceSettings';
    }

    #[Override]
    public function mandatory(): bool
    {
        return true;
    }

    #[Override]
    public function hasCustomWidth(): bool
    {
        return false;
    }

    #[Override]
    protected function useFieldset(): bool
    {
        return true;
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Commerce Settings', category: 'commerce');
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Address) {
            throw new InvalidArgumentException('UserAddressSettings can only be used in the address field layout.');
        }

        $owner = $element->getPrimaryOwner();

        if (!$owner instanceof User) {
            return null;
        }

        return
            FormFields::lightswitchFieldHtml([
                'fieldLabel' => t('Use as the primary billing address', category: 'commerce'),
                'name' => 'isPrimaryBilling',
                /** @phpstan-ignore-next-line method.notFound (getIsPrimaryBilling() is a macro registered in Plugin::registerCustomerAddressMacros(), not visible to static analysis) */
                'on' => $element->getIsPrimaryBilling(),
            ]) .
            FormFields::lightswitchFieldHtml([
                'fieldLabel' => t('Use as the primary shipping address', category: 'commerce'),
                'name' => 'isPrimaryShipping',
                /** @phpstan-ignore-next-line method.notFound (getIsPrimaryShipping() is a macro registered in Plugin::registerCustomerAddressMacros(), not visible to static analysis) */
                'on' => $element->getIsPrimaryShipping(),
            ]);
    }
}
