<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * StateTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class StateTest extends Unit
{
    /**
     * @throws InvalidConfigException
     */
    public function testGetInvalidCountry(): void
    {
        $state = new State();
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('GB');

        $this->expectException(InvalidConfigException::class);
        $state->getCountry();

        $state->countryId = $country->id;
        $stateCountry = $state->getCountry();
        self::assertIsObject($stateCountry);
        self::assertEquals($country, $stateCountry);
    }

    /**
     * @throws InvalidConfigException
     */
    public function testGetCountry(): void
    {
        $state = new State();
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('GB');
        $state->countryId = $country->id;

        $stateCountry = $state->getCountry();
        self::assertIsObject($stateCountry);
        self::assertEquals($country, $stateCountry);
    }

    /**
     *
     */
    public function testGetLabel(): void
    {
        $name = 'My State';
        $state = new State();
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('GB');
        $state->name = $name;
        $state->countryId = $country->id;
        $label = $name . ' (' . $country->name . ')';

        self::assertSame($label, $state->getLabel());
    }
}