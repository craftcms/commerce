<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\Locale as LocaleHelper;
use craft\commerce\models\Email;
use craft\commerce\models\Pdf;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Emails Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class EmailsController extends BaseAdminController
{
    /**
     * @throws InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $emails = [];
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$emails) {
            $emails[$store->handle] = Plugin::getInstance()->getEmails()->getAllEmails($store->id);
        });
        $stores = $stores->all();

        return $this->renderTemplate('commerce/settings/emails/index',[
            'stores' => $stores,
            'emails' => $emails,
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    /**
     * @param int|null $id
     * @param Email|null $email
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, Email $email = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables = compact('email', 'id');

        if (!$variables['email']) {
            if ($variables['id']) {
                $variables['email'] = Plugin::getInstance()->getEmails()->getEmailById($variables['id'], $store->id);

                if (!$variables['email']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['email'] = Craft::createObject([
                    'class' => Email::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($variables['email']->id) {
            $variables['title'] = $variables['email']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new email');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['email'], prepend: true);

        $pdfs = Plugin::getInstance()->getPdfs()->getAllPdfs($variables['email']->storeId);
        $pdfList = [null => Craft::t('commerce', 'Do not attach a PDF to this email')];
        $pdfList = ArrayHelper::merge($pdfList, $pdfs->mapWithKeys(fn(Pdf $pdf) => [$pdf->id => $pdf->name])->all());
        $variables['pdfList'] = $pdfList;
        $variables['senderAddressPlaceholder'] = App::mailSettings()->fromEmail;
        $variables['senderNamePlaceholder'] = App::mailSettings()->fromName;

        $emailLanguageOptions = [
            EmailRecord::LOCALE_ORDER_LANGUAGE => Craft::t('commerce', 'The language the order was made in.'),
        ];

        $variables['emailLanguageOptions'] = array_merge($emailLanguageOptions, LocaleHelper::getSiteAndOtherLanguages());

        $variables['readOnly'] = $this->isReadOnlyScreen();

        return $this->renderTemplate('commerce/settings/emails/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $emailsService = Plugin::getInstance()->getEmails();
        $emailId = $this->request->getBodyParam('emailId');
        $storeId = $this->request->getBodyParam('storeId');

        if (!$storeId) {
            throw new BadRequestHttpException("Invalid store ID: $storeId");
        }

        if ($emailId) {
            $email = $emailsService->getEmailById($emailId, $storeId);
            if (!$email) {
                throw new BadRequestHttpException("Invalid email ID: $emailId");
            }
        } else {
            $email = new Email();
        }

        // Shared attributes
        $email->storeId = $storeId;
        $email->name = $this->request->getBodyParam('name');
        $email->subject = $this->request->getBodyParam('subject');
        $email->recipientType = $this->request->getBodyParam('recipientType');
        $email->to = $this->request->getBodyParam('to');
        $email->bcc = $this->request->getBodyParam('bcc');
        $email->cc = $this->request->getBodyParam('cc');
        $email->replyTo = $this->request->getBodyParam('replyTo');
        $email->enabled = (bool)$this->request->getBodyParam('enabled');
        $email->templatePath = $this->request->getBodyParam('templatePath');
        $email->plainTextTemplatePath = $this->request->getBodyParam('plainTextTemplatePath');
        $pdfId = $this->request->getBodyParam('pdfId');
        $email->pdfId = $pdfId ?: null;
        $email->language = $this->request->getBodyParam('language');
        $email->setSenderAddress($this->request->getBodyParam('senderAddress'));
        $email->setSenderName($this->request->getBodyParam('senderName'));

        // Save it
        if ($emailsService->saveEmail($email)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Email saved.'));
            return $this->redirectToPostedUrl($email);
        }

        $this->setFailFlash(Craft::t('commerce', 'Couldn’t save email.'));
        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['email' => $email]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');
        if (!$id) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t delete email.'));
        }

        if (!Plugin::getInstance()->getEmails()->deleteEmailById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t delete email.'));
        }

        return $this->asSuccess();
    }
}
