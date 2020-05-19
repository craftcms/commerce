<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Test\Unit;
use craft\commerce\models\Sale;
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
    public function testGetInvalidCountry()
    {
        $state = new State();
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('GB');

        $this->expectException(InvalidConfigException::class);
        $state->getCountry();

        $state->countryId = $country->id;
        $stateCountry = $state->getCountry();
        $this->assertIsObject($stateCountry);
        $this->assertEquals($country, $stateCountry);
    }

    public function testGetCountry()
    {
        $state = new State();
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('GB');
        $state->countryId = $country->id;

        $stateCountry = $state->getCountry();
        $this->assertIsObject($stateCountry);
        $this->assertEquals($country, $stateCountry);
    }

    public function testGetLabel()
    {
        $name = 'My State';
        $state = new State();
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('GB');
        $state->name = $name;
        $state->countryId = $country->id;
        $label = $name . ' (' . $country->name . ')';

        $this->assertSame($label, $state->getLabel());
    }
}