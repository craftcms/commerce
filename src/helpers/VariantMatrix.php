<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\commerce\elements\Variant;
use craft\commerce\web\assets\variantmatrix\VariantMatrixAsset;
use craft\helpers\Html;
use craft\helpers\Json;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class VariantMatrix
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class VariantMatrix
{
    /**
     * Returns the HTML for a given product’s variant matrix.
     *
     * @param ProductElement $product The product model
     * @param string $name The input name (sans namespace). Default is 'variants'.
     * @return string The variant matrix HTML
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     * @throws InvalidConfigException
     */
    public static function getVariantMatrixHtml(ProductElement $product, string $name = 'variants'): string
    {
        $viewService = Craft::$app->getView();
        $id = Html::id($name);

        $html = $viewService->renderTemplate('commerce/products/_variant_matrix', [
            'id' => $id,
            'name' => $name,
            'variants' => $product->getVariants(),
            'product' => $product,
        ]);

        // Namespace the name/ID for JS
        $namespacedName = $viewService->namespaceInputName($name);
        $namespacedId = $viewService->namespaceInputId($id);

        $namespace = $viewService->getNamespace();
        $viewService->setNamespace(null);

        // Get the field HTML
        [$fieldBodyHtml, $fieldFootHtml] = self::_getVariantFieldHtml($product, $namespacedName);

        $viewService->registerAssetBundle(VariantMatrixAsset::class);
        $viewService->registerJs('new Craft.Commerce.VariantMatrix(' .
            '"' . $namespacedId . '", ' .
            Json::encode($fieldBodyHtml, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($fieldFootHtml, JSON_UNESCAPED_UNICODE) . ', ' .
            '"' . $namespacedName . '"' .
            ');');

        $viewService->setNamespace($namespace);

        return $html;
    }


    /**
     * Returns info about each variant field type for a variant matrix.
     *
     * @param ProductElement $product The product model
     * @param string $namespace The input namespace
     * @return array
     * @throws Exception
     * @throws InvalidConfigException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private static function _getVariantFieldHtml(ProductElement $product, string $namespace): array
    {
        $variant = new Variant();
        $variant->setProduct($product);

        $variantFields = $variant->getFieldLayout()->getFields();

        foreach ($variantFields as $fieldLayoutField) {
            $fieldLayoutField->setIsFresh(true);
        }

        $templatesService = Craft::$app->getView();
        $templatesService->startJsBuffer();

        $bodyHtml = $templatesService->renderTemplate('commerce/products/_variant_matrix_fields', [
            'namespace' => Html::namespaceInputName('__VARIANT__', $namespace),
            'variant' => $variant,
            'product' => $product,
        ]);

        $footHtml = $templatesService->clearJsBuffer();

        // Reset variant field's $_isFresh
        foreach ($variantFields as $field) {
            $field->setIsFresh();
        }

        return [$bodyHtml, $footHtml];
    }
}
