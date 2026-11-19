<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Variant\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

/**
 * The “License Expiry” fields on variants: whether the variant’s licenses expire, and for how long. It's always in
 * variant field layouts, and only shows on variants whose product type lets variants override license expiry.
 */
class VariantLicenseExpiryField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'licenseExpiry';

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    #[Override]
    public function showInForm(?ElementInterface $element = null): bool
    {
        if ($element instanceof Variant) {
            $productType = $element->getOwner()?->getType();

            if (!$productType || !$productType->hasLicenses || !$productType->hasLicenseExpiry || !$productType->allowLicenseExpiryOverride) {
                return false;
            }
        }

        return parent::showInForm($element);
    }

    #[Override]
    public function uiNode(FieldLayoutElementContext $context): ?Node
    {
        $element = $context->element;
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException(static::class . ' can only be used in variant field layouts.');
        }

        if (!$this->uid) {
            throw new InvalidArgumentException('Persisted Variant License Expiry FieldLayout elements require stable UIDs.');
        }

        $static = $context->mode !== ControlMode::Editable || $context->ui->mode !== ControlMode::Editable;

        return Group::make($this->uid, [
            Field::make(t('License Expires', category: 'commerce'))
                ->width(FieldWidth::Half)
                ->control(
                    Lightswitch::make('licenseExpires')
                        ->size('small')
                        ->value($element->licenseExpires)
                        ->mode($context->mode),
                ),
            Field::make(t('License Duration', category: 'commerce'))
                ->width(FieldWidth::Half)
                ->instructions(t('An ISO 8601 duration, such as {example}.', ['example' => Html::code('P1Y')], category: 'commerce'))
                ->control(
                    Text::make('licenseExpiryDuration')
                        ->placeholder($this->defaultDuration($element))
                        ->value($element->licenseExpiryDuration)
                        ->monospace()
                        ->mode($context->mode),
                ),
        ])
            ->asField()
            ->label($this->label())
            ->instructions($this->instructionsText($element, $static))
            ->instructionsPosition($this->instructionsPosition)
            ->tip($this->tipText($element, $static))
            ->warning($this->warningText($element, $static))
            ->layoutUid($this->uid)
            ->width($this->width);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Variant) {
            throw new InvalidArgumentException(static::class . ' can only be used in variant field layouts.');
        }

        return Html::beginTag('div', ['class' => 'flex']) .
            FormFields::lightswitchFromConfig([
                'id' => 'license-expires',
                'name' => 'licenseExpires',
                'small' => true,
                'on' => $element->licenseExpires,
                'disabled' => $static,
            ])->toHtml() .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                FormFields::textHtml([
                    'id' => 'license-expiry-duration',
                    'name' => 'licenseExpiryDuration',
                    'value' => $element->licenseExpiryDuration,
                    'placeholder' => $this->defaultDuration($element),
                    'title' => t('License Duration', category: 'commerce'),
                    'class' => 'code',
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
        Html::endTag('div');
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('License Expiry', category: 'commerce');
    }

    protected function defaultInstructions(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Whether licenses for this variant expire, and how long they last. Leave the duration blank to use the product type’s.', category: 'commerce');
    }

    private function defaultDuration(Variant $variant): string
    {
        return (string)$variant->getOwner()?->getType()->licenseExpiryDuration;
    }
}
