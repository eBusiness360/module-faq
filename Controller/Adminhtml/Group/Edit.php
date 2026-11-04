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
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Maxcode\Faq\Model\GroupFactory;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;

/**
 * Formulaire d'un groupe.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::groups';

    /**
     * @param Context       $context           Contexte du contrôleur d'administration.
     * @param PageFactory   $resultPageFactory Fabrique de pages.
     * @param GroupFactory  $groupFactory      Fabrique de groupes.
     * @param GroupResource $groupResource     Ressource des groupes.
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly GroupFactory $groupFactory,
        private readonly GroupResource $groupResource
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page|Redirect
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('group_id');
        if ($id > 0) {
            $group = $this->groupFactory->create();
            $this->groupResource->load($group, $id);
            if (!$group->getId()) {
                $this->messageManager->addErrorMessage(__('This group no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        /** @var Page $page */
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Maxcode_Faq::groups');
        $page->getConfig()->getTitle()->prepend($id > 0 ? __('Edit the group') : __('New group'));

        return $page;
    }
}
