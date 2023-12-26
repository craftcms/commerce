<?php

namespace craft\commerce\generators;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\base\ShippingRuleInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingRule;
use craft\generator\BaseGenerator;
use Nette\PhpGenerator\PhpNamespace;
use yii\helpers\Inflector;

/**
 * Creates a new shipping method.
 */
class ShippingMethod extends BaseGenerator
{
    private string $className;
    private string $namespace;
    private string $displayName;

    public function run(): bool
    {
        $this->className = $this->classNamePrompt('Shipping method name:', [
            'required' => true,
        ]);

        $this->namespace = $this->namespacePrompt('Shipping method namespace:', [
            'default' => "$this->baseNamespace\\models",
        ]);

        $this->displayName = Inflector::camel2words($this->className);

        $id = Inflector::camel2id($this->className);

        $methodNamespace = (new PhpNamespace($this->namespace))
            ->addUse(Craft::class)
            ->addUse(Order::class)
            ->addUse(Model::class)
            ->addUse(ShippingMethodInterface::class)
            ->addUse(ShippingRuleInterface::class)
            ->addUse(ShippingRule::class);

        $methodClass = $this->createClass($this->className, Model::class, [
            self::CLASS_IMPLEMENTS => [
                ShippingMethodInterface::class,
            ],
            self::CLASS_METHODS => [
                'getType' => sprintf('return \'%s\';', $id),
                'getId' => 'return null;',
                'getName' => sprintf('return \'%s\';', $this->displayName),
                'getHandle' => <<<PHP
// If you are registering multiple shipping methods, this should be unique for each instance—perhaps incorporating an ID from a third-party system:
return 'someStableIdentifier';
PHP,
                'getCpEditUrl' => "return '';",
                'getShippingRules' => <<<PHP
// Populate an array of `ShippingRule` classes from whatever data source you like. Information could come from a fulfillment or shipping API, be hard-coded, or configured via the control panel—just as the native Commerce methods and rules are!
// You may also extend the base `ShippingRule` class to store additional data and encapsulate logic.
return [
    new ShippingRule([
        'name' => 'Example {$this->displayName} Rule',
        'description' => 'This will always match.',
        'baseRate' => 10.0,
    ]),
];
PHP,
                'getIsEnabled' => 'return true;',
                'matchOrder' => <<<PHP
// Look at `\$order` and decide whether this method should match:
return true;
PHP,
                'getMatchingShippingRule' => <<<PHP
foreach (\$this->getShippingRules() as \$rule) {
    /** @var ShippingRuleInterface \$rule */
    if (\$rule->matchOrder(\$order)) {
        return \$rule;
    }
}

return null;
PHP,
                'getPriceForOrder' => <<<PHP
\$rule = \$this->getMatchingShippingRule(\$order);
// Calculate the total shipping value for the order based on the matched rule’s rates.
// See (or extend) `craft\commerce\base\ShippingMethod` for an example implementation that examines each shippable LineItem!
return \$rule->getBaseRate();
PHP,
            ],
        ]);
        $methodNamespace->add($methodClass);

        $methodClass->setComment(<<<MD
$this->displayName shipping method

This class may be instantiated any number of times when registering shipping methods. Each instance should represent a discrete method—but the source of truth about those methods can be anything.
MD);

        $this->writePhpClass($methodNamespace);
        $this->command->success("**Shipping method created!**");

        // Finish up, output instructions:
        $moduleFile = $this->moduleFile();
        $this->command->note(<<<MD
To register your shipping method, add the following code to the `init()` or `attachEventHandlers()` methods in `$moduleFile`:

```
use yii\\base\\Event;
use craft\\commerce\\services\\ShippingMethods;
use craft\\commerce\\events\\RegisterAvailableShippingMethodsEvent;
use {$this->namespace}\\{$this->className};

Event::on(
    ShippingMethods::class,
    ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS,
    function(RegisterAvailableShippingMethodsEvent \$event) {
        \$event->shippingMethods[] = new {$this->className}();
    }
);
```

You may register multiple shipping methods at once, by replacing the body of the event handler with this:

```
foreach (YourPlugin::getInstance()->getShipping()->getShippingMethods() as \$method) {
    \$event->shippingMethods[] = \$method;
}
```

The service and method we’re invoking here are your responsibility to implement.

Commerce will narrow the returned list to only those whose `matchOrder()` method returns `true`.
MD);

        $this->command->note(<<<MD
The manner in which shipping methods can be managed is up to you. Shipping “categories” and “zones” are concepts implemented by (and specific to) the flexible built-in shipping engine. Your shipping method(s) should use other criteria and management tools to customize matching.
MD);

        $this->command->warning(<<<MD
Querying an external API? Shipping methods are evaluated frequently. Don’t hold up your customers while you wait for fresh data, unless it’s *absolutely* essential.
MD);

        return true;
    }
}
