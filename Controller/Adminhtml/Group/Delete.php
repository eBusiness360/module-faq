<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Controller\Adminhtml\Group;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Maxcode\Faq\Model\GroupFactory;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;

/**
 * Supprime un groupe. Ses questions restent : elles sortent simplement du groupe.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::groups';

    /**
     * @param Context       $context       Contexte du contrôleur d'administration.
     * @param GroupFactory  $groupFactory  Fabrique de groupes.
     * @param GroupResource $groupResource Ressource des groupes.
     */
    public function __construct(
        Context $context,
        private readonly GroupFactory $groupFactory,
        private readonly GroupResource $groupResource
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('group_id');
        try {
            $group = $this->groupFactory->create();
            $this->groupResource->load($group, $id);
            if (!$group->getId()) {
                $this->messageManager->addErrorMessage(__('This group no longer exists.'));
            } else {
                $this->groupResource->delete($group);
                $this->messageManager->addSuccessMessage(__('The group was deleted. Its questions were kept.'));
            }
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('The group could not be deleted.'));

            return $redirect->setPath('*/*/edit', ['group_id' => $id]);
        }

        return $redirect->setPath('*/*/');
    }
}
