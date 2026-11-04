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
use Magento\Ui\Component\MassAction\Filter;
use Maxcode\Faq\Model\Question;
use Maxcode\Faq\Model\QuestionFactory;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;
use Maxcode\Faq\Model\ResourceModel\Question\CollectionFactory;

/**
 * Publie ou remet en brouillon les questions cochées. Une question active doit avoir une réponse : celles qui n'en ont
 * pas restent en brouillon.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::questions';

    /**
     * @param Context           $context           Contexte du contrôleur d'administration.
     * @param Filter            $filter            Sélection de la grille.
     * @param CollectionFactory $collectionFactory Questions.
     * @param QuestionFactory   $questionFactory   Fabrique de questions.
     * @param QuestionResource  $questionResource  Ressource des questions.
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
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
        $status = (int) $this->getRequest()->getParam('status') === Question::STATUS_ACTIVE
            ? Question::STATUS_ACTIVE : Question::STATUS_DRAFT;
        $done = 0;
        $refused = 0;
        try {
            foreach ($this->filter->getCollection($this->collectionFactory->create()) as $row) {
                try {
                    // On recharge la question complète : la ressource refuse une question active sans réponse.
                    $question = $this->questionFactory->create();
                    $this->questionResource->load($question, (int) $row->getId());
                    $question->setData('status', $status);
                    $this->questionResource->save($question);
                    $done++;
                } catch (\Throwable) {
                    $refused++;
                }
            }
            $this->messageManager->addSuccessMessage(__('%1 question(s) were updated.', $done));
            if ($refused > 0) {
                $this->messageManager->addWarningMessage(
                    __('%1 question(s) without an answer cannot be published and were left unchanged.', $refused)
                );
            }
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('The questions could not be updated.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
