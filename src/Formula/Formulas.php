<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Formula;

use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Twig\Environment;
use Exception;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Sandbox\SecurityPolicy;
use function CraftCms\Cms\t;

#[Singleton]
class Formulas
{
    private Environment $twigEnv;

    /**
     * @var array<string, bool> Request-level cache for condition evaluation results, keyed by formula+params hash.
     */
    private array $conditionResults = [];

    public function __construct()
    {
        $tags = $this->getTags();
        $filters = $this->getFilters();
        $functions = $this->getFunctions();
        $methods = $this->getMethods();
        $properties = $this->getProperties();

        $policy = new SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $loader = new FilesystemLoader();
        $sandbox = new SandboxExtension($policy, true);

        $this->twigEnv = new Environment($loader);
        $this->twigEnv->addExtension($sandbox);
    }

    /**
     * @param array $params data passed into the formula
     */
    public function validateConditionSyntax(string $condition, array $params): bool
    {
        try {
            $this->evaluateCondition($condition, $params, t('Validating condition syntax', category: 'commerce'));
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array $params data passed into the formula
     */
    public function validateFormulaSyntax(string $formula, array $params): bool
    {
        try {
            $this->evaluateFormula($formula, $params, null, t('Validating formula syntax', category: 'commerce'));
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array $params data passed into the condition
     * @param string $name The name of the formula, useful for locating template errors in logs and exceptions
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function evaluateCondition(string $formula, array $params, string $name = 'Evaluate Condition'): bool
    {
        if ($this->hasDisallowedStrings($formula, ['{%', '%}', '{{', '}}'])) {
            throw new SyntaxError('Tags are not allowed in a condition formula.');
        }

        $formulaHash = md5($formula);
        $paramsHash = md5(Json::encode($params));
        $requestKey = $formulaHash . $paramsHash;

        if (isset($this->conditionResults[$requestKey])) {
            return $this->conditionResults[$requestKey];
        }

        $cachedResult = Cache::get($requestKey);
        if ($cachedResult !== null) {
            return $this->conditionResults[$requestKey] = $cachedResult;
        }

        $twigCode = '{% if ';
        $twigCode .= $formula;
        $twigCode .= ' %}TRUE{% else %}FALSE{% endif %}';

        $template = $this->twigEnv->createTemplate($twigCode, $name);
        $output = $template->render($params) === 'TRUE';

        Cache::put($requestKey, $output, now()->addDay());

        return $this->conditionResults[$requestKey] = $output;
    }

    /**
     * @param array $params data passed into the condition
     * @param string|null $setType the type of the response data, passing nothing will leave as a string. Uses \settype().
     * @param string|null $name The name of the formula, useful for locating template errors in logs and exceptions
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function evaluateFormula(string $formula, array $params, ?string $setType = null, ?string $name = 'Inline formula'): mixed
    {
        $formula = trim($formula);

        $template = $this->twigEnv->createTemplate($formula, $name);
        $result = $template->render($params);

        if ($setType === null) {
            return $result;
        }

        settype($result, $setType);
        return $result;
    }

    private function hasDisallowedStrings(string $code, array $disallowedStrings = []): bool
    {
        return array_any($disallowedStrings, fn($disallowedString) => stripos($code, (string)$disallowedString) !== false);
    }

    private function getTags(): array
    {
        return [
            'for',
            'if',
            'set',
        ];
    }

    private function getFilters(): array
    {
        return [
            'abs',
            'capitalize',
            'date',
            'filter',
            'first',
            'join',
            'keys',
            'last',
            'length',
            'map',
            'merge',
            'reduce',
            'replace',
            'reverse',
            'round',
            'slice',
            'sort',
            'split',
            'trim',
            'upper',
        ];
    }

    private function getFunctions(): array
    {
        return [
            'date',
            'max',
            'min',
            'random',
            'range',
        ];
    }

    private function getMethods(): array
    {
        return [];
    }

    private function getProperties(): array
    {
        return [];
    }
}
