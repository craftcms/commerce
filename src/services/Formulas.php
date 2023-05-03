<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use craft\web\twig\Environment;
use Twig\Error\SyntaxError;

/**
 * Formulas service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class Formulas extends Component
{
    /**
     * @var array
     */
    private $_formulaConditionMatches = [];

    /**
     * @var Environment
     */
    private $_twigEnv;

    /**
     * Initialize formulas
     */
    public function init()
    {
        $tags = $this->_getTags();
        $filters = $this->_getFilters();
        $functions = $this->_getFunctions();
        $methods = $this->_getMethods();
        $properties = $this->_getProperties();

        $policy = new \Twig\Sandbox\SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $loader = new \Twig\Loader\FilesystemLoader();
        $sandbox = new \Twig\Extension\SandboxExtension($policy, true);

        $this->_twigEnv = new \Twig\Environment($loader);
        $this->_twigEnv->addExtension($sandbox);
    }

    /**
     * @oaram string $formula The condition which will be tested for correct syntax
     * @oaram array $params data passed into the formula
     * @return bool
     */
    public function validateConditionSyntax($condition, $params): bool
    {
        try {
            $this->evaluateCondition($condition, $params, Craft::t('commerce', 'Validating condition syntax'));
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * @oaram string $formula The formula which will be tested for correct syntax
     * @oaram array $params data passed into the formula
     * @return bool
     */
    public function validateFormulaSyntax($formula, $params): bool
    {
        try {
            $this->evaluateFormula($formula, $params, Craft::t('commerce', 'Validating formula syntax'));
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * @oaram string $formula
     * @oaram array $params data passed into the condition
     * @oaram string $name The name of the formula, useful for locating template errors in logs and exceptions
     * @param string $formula
     * @param $params
     * @param string $name
     * @return mixed
     * @throws SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    public function evaluateCondition(string $formula, $params, $name = 'Evaluate Condition'): bool
    {
        if ($this->_hasDisallowedStrings($formula, ['{%', '%}', '{{', '}}'])) {
            throw new SyntaxError('Tags are not allowed in a condition formula.');
        }

        $cacheKey = [
            'formula' => md5($formula),
            'params' => md5(Json::encode($params)),
        ];

        $cachedResult = Craft::$app->getCache()->get($cacheKey);
        if ($cachedResult !== false) {
            return $cachedResult === 'TRUE';
        }

        $twigCode = '{% if ';
        $twigCode .= $formula;
        $twigCode .= ' %}TRUE{% else %}FALSE{% endif %}';

        $template = $this->_twigEnv->createTemplate($twigCode, $name);
        $output = $template->render($params);

        Craft::$app->getCache()->set($cacheKey, $output);

        return $output === 'TRUE';
    }

    /**
     * @oaram string $formula
     * @oaram array $params data passed into the condition
     * @oaram string|null $setType the type of the response data, passing nothing will leave as a string. Uses \settype().
     * @oaram string|null $name The name of the formula, useful for locating template errors in logs and exceptions
     * @return mixed
     * @throws SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    public function evaluateFormula(string $formula, $params, $setType = null, $name = 'Inline formula'): bool
    {
        $formula = trim($formula);

        $template = $this->_twigEnv->createTemplate((string)$formula, $name);
        $result = $template->render($params);

        if ($setType === null) {
            return $result;
        }

        if (settype($result, $setType)) {
            return $result;
        }

        return $result;
    }

    /**
     * @param string $code
     * @param array $disallowedStrings
     * @return bool
     */
    private function _hasDisallowedStrings(string $code, $disallowedStrings = [])
    {
        foreach ($disallowedStrings as $disallowedString) {
            if (stripos($code, $disallowedString) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    private function _getTags(): array
    {
        $tags = [
            //'apply',
            //'autoescape',
            //'block',
            //'deprecated',
            //'do',
            //'embed',
            //'extends',
            //'flush',
            'for',
            //'from',
            'if',
            //'import',
            //'include',
            //'macro',
            //'sandbox',
            'set',
            //'use',
            //'verbatim',
            //'with',
        ];
        return $tags;
    }

    /**
     * @return array
     */
    private function _getFilters(): array
    {
        $filters = [
            'abs',
            //'batch',
            'capitalize',
            //'column',
            //'convert_encoding',
            //'country_name',
            //'country_timezones',
            //'currency_name',
            //'currency_symbol',
            //'data_uri',
            'date',
            //'date_modify',
            //'default',
            //'escape',
            'filter',
            'first',
            //'format',
            //'format_currency',
            //'format_date',
            //'format_datetime',
            //'format_number',
            //'format_time',
            //'inky',
            //'inline_css',
            'join',
            //'json_encode',
            'keys',
            //'language_name',
            'last',
            'length',
            //'locale_name',
            //'lower',
            'map',
            //'markdown',
            'merge',
            //'nl2br',
            //'number_format',
            //'raw',
            'reduce',
            'replace',
            'reverse',
            'round',
            'slice',
            'sort',
            //'spaceless',
            'split',
            //'striptags',
            //'timezone_name',
            //'title',
            'trim',
            'upper',
            //'url_encode',
        ];
        return $filters;
    }

    /**
     * @return array
     */
    private function _getFunctions(): array
    {
        $functions = [
            //'attribute',
            //'block',
            //'constant',
            //'cycle',
            'date',
            //'dump',
            //'html_classes',
            //'include',
            'max',
            'min',
            //'parent',
            'random',
            'range',
            //'source',
            //'template_from_string',
        ];
        return $functions;
    }

    /**
     * @return array
     */
    private function _getMethods(): array
    {
        $methods = [];
        return $methods;
    }

    /**
     * @return array
     */
    private function _getProperties(): array
    {
        $properties = [];
        return $properties;
    }
}
