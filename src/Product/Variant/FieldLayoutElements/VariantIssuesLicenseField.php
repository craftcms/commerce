<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

/**
 * The “Issues License” lightswitch on variants. It's always in variant field layouts, and only shows on variants
 * whose product type allows licenses.
 */
class VariantIssuesLicenseField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'issuesLicense';

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    #[Override]
    public function showInForm(?ElementInterface $element = null): bool
    {
        if ($element instanceof Variant && !$element->getOwner()?->getType()->hasLicenses) {
            return false;
        }

        return parent::showInForm($element);
    }

    #[Override]
    protected function uiControl(FieldLayoutElementContext $context): ?Control
    {
        $element = $context->element;
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException(static::class . ' can only be used in variant field layouts.');
        }

        return Lightswitch::make('issuesLicense')
            ->size('small')
            ->value($element->issuesLicense);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException(static::class . ' can only be used in variant field layouts.');
        }

        return FormFields::lightswitchFromConfig([
            'id' => 'issues-license',
            'name' => 'issuesLicense',
            'small' => true,
            'on' => $element->issuesLicense,
            'disabled' => $static,
        ])->toHtml();
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Issues License', category: 'commerce');
    }

    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Whether purchasing this variant issues a license for each unit.', category: 'commerce');
    }
}
