<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Address\Conditions;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Form\Contracts\Node;
use CraftCms\Cms\Form\Controls\Textarea;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Commerce\Formula\Formulas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

use function CraftCms\Cms\t;

class PostalCodeFormulaConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Postal Code Formula', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        throw new LogicException('Discount Address Condition does not support element queries.');
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Address $element */
        $formula = $this->value;
        $postalCode = $element->postalCode;

        try {
            return (bool)app(Formulas::class)->evaluateCondition($formula, ['postalCode' => $postalCode], 'Postal code formula matching address');
        } catch (Throwable) {
            Log::error('Error evaluating postal code formula: ' . $formula);

            return false;
        }
    }

    #[\Override]
    protected function operators(): array
    {
        return [
            self::OPERATOR_EQ,
        ];
    }

    /** @return list<Node> */
    #[\Override]
    protected function inputNodes(): array
    {
        return [Field::make($this->getLabel(), Textarea::make('value')->value($this->value)->monospace())];
    }
}
