<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Enums\FieldWidth;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableAllowedQtyField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'allowedQty';

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        unset($config['required']);
        parent::__construct($config);
    }

    #[Override]
    public function formNode(FieldLayoutElementContext $context): ?Node
    {
        $element = $context->element;
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        if (!$this->uid) {
            throw new InvalidArgumentException('Persisted Purchasable Allowed Quantity FieldLayout elements require stable UIDs.');
        }

        $static = $context->mode !== \CraftCms\Cms\Form\Enums\ControlMode::Editable;
        $status = $this->showStatus() ? $this->statusClass($element, $static) : null;
        $statusLabel = $status !== null
            ? ($this->statusLabel($element, $static) ?? ucfirst($status))
            : null;

        return Group::make($this->uid, [
            Field::make(t('Minimum allowed quantity', category: 'commerce'))
                ->status($status, $statusLabel)
                ->width(FieldWidth::Half)
                ->control(
                    Number::make('minQty')
                        ->placeholder(t('Any', category: 'commerce'))
                        ->value($element->minQty)
                        ->mode($context->mode)
                        ->reactive(),
                ),
            Field::make(t('Maximum allowed quantity', category: 'commerce'))
                ->status($status, $statusLabel)
                ->width(FieldWidth::Half)
                ->control(
                    Number::make('maxQty')
                        ->placeholder(t('Any', category: 'commerce'))
                        ->value($element->maxQty)
                        ->mode($context->mode)
                        ->reactive(),
                ),
        ])
            ->asField()
            ->label($this->label())
            ->instructions($this->instructionsText($element))
            ->instructionsPosition($this->instructionsPosition)
            ->tip($this->tipText($element))
            ->warning($this->warningText($element))
            ->required($this->required)
            ->layoutUid($this->uid)
            ->width($this->width);
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        return Html::beginTag('div', ['class' => 'flex']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                FormFields::textHtml([
                    'id' => 'minQty',
                    'name' => 'minQty',
                    'value' => $element->minQty,
                    'placeholder' => t('Any', category: 'commerce'),
                    'title' => t('Minimum allowed quantity', category: 'commerce'),
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
            Html::tag('div', t('to', category: 'commerce'), ['class' => 'label light']) .
            Html::beginTag('div', ['class' => 'textwrapper']) .
                FormFields::textHtml([
                    'id' => 'maxQty',
                    'name' => 'maxQty',
                    'value' => $element->maxQty,
                    'placeholder' => t('Any', category: 'commerce'),
                    'title' => t('Maximum allowed quantity', category: 'commerce'),
                    'disabled' => $static,
                ]) .
            Html::endTag('div') .
        Html::endTag('div');
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Allowed Qty', category: 'commerce');
    }
}
