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
use Maxcode\Faq\Model\ResourceModel\Question\CollectionFactory;

/**
 * Supprime les questions cochées dans la grille.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::questions';

    /**
     * @param Context           $context           Contexte du contrôleur d'administration.
     * @param Filter            $filter            Sélection de la grille.
     * @param CollectionFactory $collectionFactory Questions.
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $count = 0;
        try {
            foreach ($this->filter->getCollection($this->collectionFactory->create()) as $question) {
                $question->delete();
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 question(s) were deleted.', $count));
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('The questions could not be deleted.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
