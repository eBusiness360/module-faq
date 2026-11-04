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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Maxcode\Faq\Model\Admin\PostMapper;
use Maxcode\Faq\Model\GroupFactory;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;

/**
 * Enregistre un groupe.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::groups';
    public const PERSISTOR_KEY = 'maxcode_faq_group';

    /**
     * @param Context                $context       Contexte du contrôleur d'administration.
     * @param GroupFactory           $groupFactory  Fabrique de groupes.
     * @param GroupResource          $groupResource Ressource des groupes.
     * @param PostMapper             $postMapper    Données du formulaire vers données du modèle.
     * @param DataPersistorInterface $dataPersistor Garde la saisie en cas d'erreur.
     */
    public function __construct(
        Context $context,
        private readonly GroupFactory $groupFactory,
        private readonly GroupResource $groupResource,
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
        $id = (int) ($post['group_id'] ?? 0);
        try {
            $group = $this->groupFactory->create();
            if ($id > 0) {
                $this->groupResource->load($group, $id);
                if (!$group->getId()) {
                    throw new LocalizedException(__('This group no longer exists.'));
                }
            }
            $group->addData($this->postMapper->group($post));
            $this->groupResource->save($group);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The group was saved.'));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['group_id' => $group->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('The group could not be saved.'));
        }
        $this->dataPersistor->set(self::PERSISTOR_KEY, $post);

        return $id > 0
            ? $redirect->setPath('*/*/edit', ['group_id' => $id])
            : $redirect->setPath('*/*/new');
    }
}
