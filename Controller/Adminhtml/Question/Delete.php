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
use Maxcode\Faq\Model\QuestionFactory;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;

/**
 * Supprime une question.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::questions';

    /**
     * @param Context          $context          Contexte du contrôleur d'administration.
     * @param QuestionFactory  $questionFactory  Fabrique de questions.
     * @param QuestionResource $questionResource Ressource des questions.
     */
    public function __construct(
        Context $context,
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('question_id');
        try {
            $question = $this->questionFactory->create();
            $this->questionResource->load($question, $id);
            if (!$question->getId()) {
                $this->messageManager->addErrorMessage(__('This question no longer exists.'));
            } else {
                $this->questionResource->delete($question);
                $this->messageManager->addSuccessMessage(__('The question was deleted.'));
            }
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('The question could not be deleted.'));

            return $redirect->setPath('*/*/edit', ['question_id' => $id]);
        }

        return $redirect->setPath('*/*/');
    }
}
