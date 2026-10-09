<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\FieldLayoutElements;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\FieldLayout\LayoutElements\BaseNativeField;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\FieldWidth;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use InvalidArgumentException;
use Override;

use function CraftCms\Cms\t;

class PurchasableDimensionsField extends BaseNativeField
{
    #[Override]
    public bool $mandatory = true;

    #[Override]
    public string $attribute = 'dimensions';

    #[Override]
    protected function showLabel(): bool
    {
        return false;
    }

    #[Override]
    public function showInForm(?ElementInterface $element = null): bool
    {
        if ($element instanceof Variant && !$element->getOwner()->getType()->hasDimensions) {
            return false;
        }

        return parent::showInForm($element);
    }

    #[Override]
    public function uiNode(FieldLayoutElementContext $context): ?Node
    {
        $element = $context->element;
        if (!$element instanceof Purchasable) {
            throw new InvalidArgumentException(static::class . ' can only be used in purchasable field layouts.');
        }

        if (!$this->uid) {
            throw new InvalidArgumentException('Persisted Purchasable Dimensions FieldLayout elements require stable UIDs.');
        }

        $unit = app(Plugin::class)->getSettings()->dimensionUnits;

        $static = $context->mode !== \CraftCms\Cms\Ui\Enums\ControlMode::Editable;
        $status = $this->showStatus() ? $this->statusClass($element, $static) : null;
        $statusLabel = $status !== null
            ? ($this->statusLabel($element, $static) ?? ucfirst($status))
            : null;
        $localized = fn(?float $value): string => $value === null ? '' : I18N::getFormatter()->asDecimal($value);

        return Group::make($this->uid, [
            Field::make(t('Length', category: 'commerce'))
                ->status($status, $statusLabel)
                ->width(FieldWidth::Third)
                ->control(Text::make('length')->value($localized($element->length))->inputMode('decimal')->suffix($unit)->mode($context->mode)->reactive()),
            Field::make(t('Width', category: 'commerce'))
                ->status($status, $statusLabel)
                ->width(FieldWidth::Third)
                ->control(Text::make('width')->value($localized($element->width))->inputMode('decimal')->suffix($unit)->mode($context->mode)->reactive()),
            Field::make(t('Height', category: 'commerce'))
                ->status($status, $statusLabel)
                ->width(FieldWidth::Third)
                ->control(Text::make('height')->value($localized($element->height))->inputMode('decimal')->suffix($unit)->mode($context->mode)->reactive()),
        ])
            ->asField()
            ->label($this->label !== null && $this->label !== '__blank__' ? $this->label() : null)
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

        $dimensionUnits = app(Plugin::class)->getSettings()->dimensionUnits;

        return Html::beginTag('div', ['class' => 'flex']) .
            FormFields::fieldHtml(FormFields::textHtml([
                'id' => 'length',
                'name' => 'length',
                'value' => $element->length !== null ? I18N::getFormatter()->asDecimal($element->length) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => $dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'length', 'label' => t('Length', category: 'commerce')]) .
            FormFields::fieldHtml(FormFields::textHtml([
                'id' => 'width',
                'name' => 'width',
                'value' => $element->width !== null ? I18N::getFormatter()->asDecimal($element->width) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => $dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'width', 'label' => t('Width', category: 'commerce')]) .
            FormFields::fieldHtml(FormFields::textHtml([
                'id' => 'height',
                'name' => 'height',
                'value' => $element->height !== null ? I18N::getFormatter()->asDecimal($element->height) : '',
                'class' => 'text',
                'size' => 10,
                'unit' => $dimensionUnits,
                'disabled' => $static,
            ]), ['id' => 'height', 'label' => t('Height', category: 'commerce')]) .
        Html::endTag('div');
    }

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return t('Dimensions', category: 'commerce');
    }
}
