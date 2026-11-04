<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Controller\Adminhtml\Question;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Maxcode\Faq\Model\Admin\PostMapper;
use Maxcode\Faq\Model\QuestionFactory;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;

/**
 * Enregistre une question.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::questions';
    public const PERSISTOR_KEY = 'maxcode_faq_question';

    /**
     * @param Context                $context          Contexte du contrôleur d'administration.
     * @param QuestionFactory        $questionFactory  Fabrique de questions.
     * @param QuestionResource       $questionResource Ressource des questions.
     * @param PostMapper             $postMapper       Données du formulaire vers données du modèle.
     * @param DataPersistorInterface $dataPersistor    Garde la saisie en cas d'erreur.
     */
    public function __construct(
        Context $context,
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource,
        private readonly PostMapper $postMapper,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $post = (array) $this->getRequest()->getPostValue();
        if (!$post) {
            return $redirect->setPath('*/*/');
        }
        $id = (int) ($post['question_id'] ?? 0);
        try {
            $question = $this->questionFactory->create();
            if ($id > 0) {
                $this->questionResource->load($question, $id);
                if (!$question->getId()) {
                    throw new LocalizedException(__('This question no longer exists.'));
                }
            }
            $mapped = $this->postMapper->question($post);
            $question->addData($mapped['data']);
            $this->questionResource->save($question);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The question was saved.'));
            if ($mapped['unknown_skus']) {
                $this->messageManager->addWarningMessage(__(
                    'These product references do not exist and were ignored: %1',
                    implode(', ', array_slice($mapped['unknown_skus'], 0, 20))
                ));
            }
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['question_id' => $question->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('The question could not be saved.'));
        }
        $this->dataPersistor->set(self::PERSISTOR_KEY, $post);

        return $id > 0
            ? $redirect->setPath('*/*/edit', ['question_id' => $id])
            : $redirect->setPath('*/*/new');
    }
}
