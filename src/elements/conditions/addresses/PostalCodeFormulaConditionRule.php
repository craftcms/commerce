<?php

namespace craft\commerce\elements\conditions\addresses;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\errors\NotImplementedException;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use yii\base\NotSupportedException;

/**
 * Total Price Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 */
class PostalCodeFormulaConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Post Code Formula');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Discount Address Condition does not support element queries.');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {

    }

    public function inputHtml(): string
    {
        $html = '';
        Cp::fieldHtml($html,[
            'label' => Craft::t('commerce', 'Postal Code Formula'),
            'rows' => 3,
            'instruction' => Craft::t('commerce',
                'Specify a <a href="{url}">Twig condition</a> that determines whether the shipping zone should include a given Post Code. (The Zip/postal code can be referenced via a `zipCode` variable.)',
                ['url' => 'https://twig.symfony.com/doc/2.x/advanced.html#conditionals']
    ),
        ]);
    }
}