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
use Exception;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Sandbox\SecurityPolicy;

/**
 * Formulas service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class Formulas extends Component
{
    /**
     * @var Environment
     */
    private Environment $_twigEnv;

    /**
     * Initialize formulas
     */
    public function init(): void
    {
        $tags = $this->_getTags();
        $filters = $this->_getFilters();
        $functions = $this->_getFunctions();
        $methods = $this->_getMethods();
        $properties = $this->_getProperties();

        $policy = new SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $loader = new FilesystemLoader();
        $sandbox = new SandboxExtension($policy, true);

        $this->_twigEnv = new Environment($loader);
        $this->_twigEnv->addExtension($sandbox);
    }

    /**
     * @param string $condition The condition which will be tested for correct syntax
     * @param array $params data passed into the formula
     */
    public function validateConditionSyntax(string $condition, array $params): bool
    {
        try {
            $this->evaluateCondition($condition, $params, Craft::t('commerce', 'Validating condition syntax'));
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param string $formula The formula which will be tested for correct syntax
     * @param array $params data passed into the formula
     */
    public function validateFormulaSyntax(string $formula, array $params): bool
    {
        try {
            $this->evaluateFormula($formula, $params, null, Craft::t('commerce', 'Validating formula syntax'));
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array $params data passed into the condition
     * @param string $name The name of the formula, useful for locating template errors in logs and exceptions
     * @return bool
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function evaluateCondition(string $formula, array $params, string $name = 'Evaluate Condition'): bool
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
     * @param string $formula
     * @param array $params data passed into the condition
     * @param string|null $setType the type of the response data, passing nothing will leave as a string. Uses \settype().
     * @param string|null $name The name of the formula, useful for locating template errors in logs and exceptions
     * @return mixed
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function evaluateFormula(string $formula, array $params, ?string $setType = null, ?string $name = 'Inline formula'): mixed
    {
        $formula = trim($formula);

        $template = $this->_twigEnv->createTemplate($formula, $name);
        $result = $template->render($params);

        if ($setType === null) {
            return $result;
        }

        settype($result, $setType);
        return $result;
    }

    private function _hasDisallowedStrings(string $code, array $disallowedStrings = []): bool
    {
        foreach ($disallowedStrings as $disallowedString) {
            if (stripos($code, (string) $disallowedString) !== false) {
                return true;
            }
        }
        return false;
    }

    private function _getTags(): array
    {
        return [
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
    }

    private function _getFilters(): array
    {
        return [
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
    }

    private function _getFunctions(): array
    {
        return [
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
    }

    private function _getMethods(): array
    {
        return [];
    }

    private function _getProperties(): array
    {
        return [];
    }
}
