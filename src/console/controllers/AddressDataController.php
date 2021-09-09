<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Craft;
use craft\base\Model;
use craft\commerce\console\Controller;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\State as StateRecord;
use yii\console\ExitCode;

/**
 * Allows you to populate Commerce Address data.
 * 
 * @todo
 * 1.  countryIsos params with comma separated country code E.g. US, CA, UK. Loop over given country and generate countries with its states. 
 If country exists it should prompt to generate the states for that country. If a state already exist by checking name, isoCode and code, skip it with error message.
 * 2. If countryIsos params not given a prompt of a single Country ISO that generates states for that country.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressDataController extends Controller
{
    public $defaultAction = 'populate';
    // Changed to prompt
    public string $countryIso = 'US';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'countryIso';

        return $options;
    }
    
    /**
     * 
     *
     * @return int
     */
    public function actionPopulate(): int
    {
        $country = Plugin::getInstance()->getCountries()->getCountryByIso($this->countryIso);
        
        if ($country !== null) {
            $subdivisionRepository = new SubdivisionRepository();

            $subdivisions = $subdivisionRepository->getAll([$this->countryIso]);
            
            $statesService = Plugin::getInstance()->getStates();
            $sortCount = 1;
            foreach ($subdivisions as $subdivision) {
                
                $subdivisionName = $subdivision->getName();
                
                $stateRecord = StateRecord::findOne(['name' => $subdivisionName]);
                // trim and lowercase the search.
                // search for name, iso code and code in order
                // Only if 3 of the search don't exist, create the state.
                if ($stateRecord === null) {
                  //  $state = $statesService->getStateById($stateRecord->id);
               // } else {
                    $state = new State();
                    $state->id = null;

                    $state->name = $subdivisionName;

                    $state->abbreviation = $subdivision->getIsoCode() ?: $subdivision->getCode();
                    $state->countryId = $country->id;
                    $state->enabled = 1;
                    $state->sortOrder = $sortCount;
                    $sortCount++;

                    if ($statesService->saveState($state) === true) {
                        $this->stdout(' Subdivision ' . $subdivisionName . ' ' . ($stateRecord !== null ? 'updated' : 'added' ) . ' to the country ' . $country->name . PHP_EOL);
                    }
                } else {
                    $this->stderr("Didn't add state");
                }
            }
        } else {
            $this->stdout('Invalid Country ISO given: ' . $this->countryIso . PHP_EOL);
        }       

        
        return ExitCode::OK;
    }
}
