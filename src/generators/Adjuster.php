<?php

namespace craft\commerce\generators;

use Craft;
use Nette\PhpGenerator\PhpNamespace;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\services\OrderAdjustments;
use craft\generator\BaseGenerator;
use Illuminate\Support\Collection;
use yii\helpers\Inflector;

/**
 * Creates a new order adjuster class.
 */
class Adjuster extends BaseGenerator
{
    private string $className;
    private string $namespace;
    private string $displayName;

    public function run(): bool
    {
        $this->className = $this->classNamePrompt('Adjuster name:', [
            'required' => true,
        ]);

        $this->namespace = $this->namespacePrompt('Adjuster namespace:', [
            'default' => "$this->baseNamespace\\adjusters",
        ]);

        $this->displayName = Inflector::camel2words($this->className);

        $namespace = (new PhpNamespace($this->namespace))
            ->addUse(Craft::class)
            ->addUse(Component::class)
            ->addUse(AdjusterInterface::class)
            ->addUse(OrderAdjustment::class)
            ->addUse(Currency::class);

        $class = $this->createClass($this->className, Component::class, [
            self::CLASS_IMPLEMENTS => [
                AdjusterInterface::class,
            ],
            self::CLASS_METHODS => [
                'adjust' => <<<PHP
\$adjustments = [];

// This adjuster only cares about individual items. Yours may need to perform deeper analysis of the cart’s contents to determine whether an item (or items) need an adjustment, or which item (if any) the adjustment should be applied to.
foreach (\$order->getLineItems() as \$lineItem) {
    if (\$lineItem->qty >= 10) {
        \$adjustment = new OrderAdjustment();
        \$adjustment->setLineItem(\$lineItem); // Optional!
        \$adjustment->setOrder(\$order);
        \$adjustment->type = 'discount';
        \$adjustment->amount = Currency::round(\$lineItem->qty * \$lineItem->salePrice * -0.1);
        \$adjustment->name = 'Bulk Discount';
        \$adjustment->description = '10% off when you buy 10 or more!';

        \$adjustments[] = \$adjustment;
    }
}

return \$adjustments;
PHP,
            ],
        ]);
        $namespace->add($class);

        $class->setComment(<<<MD
$this->displayName adjuster

Grants 10% off when ordering in bulk.
MD);

        $this->writePhpClass($namespace);

        if (
            $this->isForModule() &&
            !$this->addRegistrationEventHandlerCode(
                OrderAdjustments::class,
                'EVENT_REGISTER_ORDER_ADJUSTERS',
                "$this->namespace\\$this->className",
                $fallbackExample,
            )
        ) {
            $moduleFile = $this->moduleFile();
            $this->command->note(<<<MD
Add the following code to `$moduleFile` to register the adjuster:

```
$fallbackExample
```
MD);
        }

        $this->command->success("**Adjuster created!**");
        return true;
    }
}
