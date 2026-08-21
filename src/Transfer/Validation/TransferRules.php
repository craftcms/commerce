<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Validation;

use CraftCms\Cms\Element\Validation\ElementRules;
use Illuminate\Validation\Rule;

/**
 * Transfer's legacy `defineRules()` also had `validateLocations` and `validateDetails` imperative
 * validators that add errors across multiple attributes rather than validating a single value;
 * those stay as plain methods on {@see \CraftCms\Commerce\Transfer\Elements\Transfer} itself, wired
 * up via {@see \CraftCms\Cms\Validation\Ruleset::after()}. This ruleset only covers the type/required
 * rules that were plain validators in the legacy `defineRules()`.
 */
class TransferRules extends ElementRules
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['originLocationId'] = Rule::when(
            $this->inScenarios(self::SCENARIO_LIVE),
            ['required', 'integer'],
        );

        $rules['destinationLocationId'] = Rule::when(
            $this->inScenarios(self::SCENARIO_LIVE),
            ['required', 'integer'],
        );

        return $rules;
    }
}
