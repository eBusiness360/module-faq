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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;

/**
 * « Ajouter une question » : le formulaire d'édition, vide.
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Maxcode_Faq::questions';

    /**
     * @param Context        $context        Contexte du contrôleur d'administration.
     * @param ForwardFactory $forwardFactory Redirection interne.
     */
    public function __construct(Context $context, private readonly ForwardFactory $forwardFactory)
    {
        parent::__construct($context);
    }

    /**
     * @return Forward
     */
    public function execute(): Forward
    {
        return $this->forwardFactory->create()->forward('edit');
    }
}
