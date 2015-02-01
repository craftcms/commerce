<?php
namespace Craft;
use Stripey\Product\Creator;

/**
 * Class Stripey_ProductController
 *
 * @package Craft
 */
class Stripey_ProductController extends Stripey_BaseController
{
    /** @var bool All product changes should be by a logged in user */
    protected $allowAnonymous = false;

    /**
     * Index of products
     */
    public function actionProductIndex()
    {
        $variables['productTypes'] = craft()->stripey_productType->getAll();
        $this->renderTemplate('stripey/products/_index', $variables);
    }

    /**
     * Prepare screen to edit a product.
     *
     * @param array $variables
     * @throws HttpException
     */
    public function actionEditProduct(array $variables = array())
    {
        if (!empty($variables['productTypeHandle'])) {
            $variables['productType'] = craft()->stripey_productType->getByHandle($variables['productTypeHandle']);
        }

        if(empty($variables['productType'])) {
            throw new HttpException(400, craft::t('Wrong product type specified'));
        }

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = craft()->stripey_product->getById($variables['productId']);
                $variables['masterVariant'] = $variables['product']->masterVariant;

                if (!$variables['product']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['product']         = new Stripey_ProductModel();
                $variables['product']->typeId = $variables['productType']->id;
            };
        }

        if (!empty($variables['productId'])) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('Create a new Product');
        }
        $this->prepVariables($variables);

        $this->renderTemplate('stripey/products/_edit', $variables);
    }

    /**
     * Save a new or existing product.
     */
    public function actionSaveProduct()
    {
        $this->requirePostRequest();

        $product = $this->_setProductFromPost();
        $this->_setContentFromPost($product);
        $masterVariant = $this->_setMasterVariantFromPost($product);
        $optionTypes = craft()->request->getPost('optionTypes');

        $productCreator = new Creator;

        $transaction = craft()->db->beginTransaction();

        if ($productCreator->save($product)) {
            $masterVariant->productId = $product->id;

            if (craft()->stripey_variant->save($masterVariant)) {
                craft()->stripey_product->setOptionTypes($product->id, $optionTypes);
                $transaction->commit();

                craft()->userSession->setNotice(Craft::t('Product saved.'));

                if (craft()->request->getPost('redirectToVariant')) {
                    $this->redirect($product->getCpEditUrl() . '/variants/new');
                } else {
                    $this->redirectToPostedUrl($product);
                }
            }
        }

        $transaction->rollback();

        craft()->userSession->setNotice(Craft::t("Couldn't save product."));
        craft()->urlManager->setRouteVariables(array(
            'product' => $product,
            'masterVariant' => $masterVariant
        ));
    }

    /**
     * Modifies the variables of the request.
     *
     * @param $variables
     */
    private function prepVariables(&$variables)
    {
        $variables['tabs'] = array();

        $variables['masterVariant'] = $variables['product']->masterVariant;

        foreach ($variables['productType']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['product']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($variables['product']->getErrors($field->getField()->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = array(
                'label' => Craft::t($tab->name),
                'url'   => '#tab' . ($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            );
        }
    }

    /**
     * Deletes a product.
     *
     * @throws Exception if you try to edit a non existing Id.
     */
    public function actionDeleteProduct()
    {
        $this->requirePostRequest();

        $productId = craft()->request->getRequiredPost('productId');
        $product   = craft()->stripey_product->getById($productId);

        if (!$product) {
            throw new Exception(Craft::t('No product exists with the ID “{id}”.', array('id' => $productId)));
        }

        if (craft()->stripey_product->delete($product)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(array('success' => true));
            } else {
                craft()->userSession->setNotice(Craft::t('Product deleted.'));
                $this->redirectToPostedUrl($product);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(array('success' => false));
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t delete product.'));

                craft()->urlManager->setRouteVariables(array(
                    'product' => $product

                ));
            }
        }
    }

    /**
     * @param Stripey_ProductModel $product
     * @return Stripey_VariantModel
     */
    private function _setMasterVariantFromPost($product)
    {
        $attributes = craft()->request->getPost('masterVariant');

        $masterVariant = $product->masterVariant;
        $masterVariant->setAttributes($attributes);
        $masterVariant->isMaster = true;
        return $masterVariant;
    }

    /**
     * @return Stripey_ProductModel
     * @throws Exception
     */
    private function _setProductFromPost()
    {
        $productId = craft()->request->getPost('productId');

        if ($productId) {
            $product = craft()->stripey_product->getById($productId);

            if (!$product) {
                throw new Exception(Craft::t('No product with the ID “{id}”', array('id' => $productId)));
            }
        } else {
            $product = new Stripey_ProductModel();
        }

        $product->availableOn = ($availableOn = craft()->request->getPost('availableOn')) ? DateTime::createFromString($availableOn, craft()->timezone) : $product->availableOn;
        $product->expiresOn   = ($expiresOn = craft()->request->getPost('expiresOn')) ? DateTime::createFromString($expiresOn, craft()->timezone) : null;
        $product->typeId      = craft()->request->getPost('typeId');
        $product->enabled     = craft()->request->getPost('enabled');
        $product->authorId    = craft()->userSession->id;

        if (!$product->availableOn) {
            $product->availableOn = new DateTime();
        }

        return $product;
    }

    /**
     * @param Stripey_ProductModel $product
     */
    private function _setContentFromPost($product)
    {
        $product->getContent()->title = craft()->request->getPost('title', $product->title);
        $product->setContentFromPost('fields');
    }

} 