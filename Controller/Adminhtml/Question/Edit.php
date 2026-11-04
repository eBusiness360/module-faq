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
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Maxcode\Faq\Model\QuestionFactory;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;

/**
 * Formulaire d'une question.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::questions';

    /**
     * @param Context          $context           Contexte du contrôleur d'administration.
     * @param PageFactory      $resultPageFactory Fabrique de pages.
     * @param QuestionFactory  $questionFactory   Fabrique de questions.
     * @param QuestionResource $questionResource  Ressource des questions.
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page|Redirect
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('question_id');
        if ($id > 0) {
            $question = $this->questionFactory->create();
            $this->questionResource->load($question, $id);
            if (!$question->getId()) {
                $this->messageManager->addErrorMessage(__('This question no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        /** @var Page $page */
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Maxcode_Faq::questions');
        $page->getConfig()->getTitle()->prepend($id > 0 ? __('Edit the question') : __('New question'));

        return $page;
    }
}
