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
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressDataController extends Controller
{
    public $defaultAction = 'populate';
    
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
            foreach ($subdivisions as $subdivision) {
                
                $subdivisionName = $subdivision->getName();
                
                $stateRecord = StateRecord::findOne(['name' => $subdivisionName]);
                
                if ($stateRecord !== null) {
                    $state = $statesService->getStateById($stateRecord->id);
                } else {
                    $state = new State();
                    $state->id = null;
                }
                
                $state->name = $subdivisionName;
          
                $state->abbreviation = $subdivision->getCode();
                $state->countryId = $country->id;
                $state->enabled = 1;
                
                if ($statesService->saveState($state) === true) {
                    $this->stdout(' Subdivision ' . $subdivisionName . ' ' . ($stateRecord !== null ? 'updated' : 'added' ) . ' to the country ' . $country->name . PHP_EOL);
                }
            }
        } else {
            $this->stdout('Invalid Country ISO given: ' . $this->countryIso . PHP_EOL);
        }       

        
        return ExitCode::OK;
    }
}
