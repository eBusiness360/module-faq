<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Bouton d'un formulaire d'administration : retour, suppression, enregistrement, enregistrement et poursuite.
 * Déclaré en DI une fois par formulaire (type de bouton, route, nom du paramètre d'identifiant, nom du formulaire).
 */
class FormButton implements ButtonProviderInterface
{
    public const BACK = 'back';
    public const DELETE = 'delete';
    public const SAVE = 'save';
    public const SAVE_CONTINUE = 'save_continue';

    /**
     * @param Context $context Contexte d'administration.
     * @param string  $type    back, delete, save ou save_continue.
     * @param string  $route   Route du contrôleur (« maxcode_faq/question »).
     * @param string  $idParam Nom du paramètre d'identifiant dans l'adresse.
     * @param string  $form    Nom du formulaire (composant d'interface).
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $type = self::SAVE,
        private readonly string $route = '',
        private readonly string $idParam = 'id',
        private readonly string $form = ''
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getButtonData(): array
    {
        $id = (int) $this->context->getRequest()->getParam($this->idParam);
        $url = fn (string $action, array $params = []): string =>
            $this->context->getUrlBuilder()->getUrl($this->route . '/' . $action, $params);

        return match ($this->type) {
            self::BACK => [
                'label' => __('Back'),
                'on_click' => sprintf("location.href = '%s';", $url('index')),
                'class' => 'back',
                'sort_order' => 10,
            ],
            self::DELETE => $id > 0 ? [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . $this->context->getEscaper()->escapeJs(
                    $this->context->getEscaper()->escapeHtml(__('Are you sure you want to do this?'))
                ) . '\', \'' . $url('delete', [$this->idParam => $id]) . '\', {"data": {}})',
                'sort_order' => 20,
            ] : [],
            self::SAVE_CONTINUE => [
                'label' => __('Save and continue editing'),
                'class' => 'save',
                'data_attribute' => ['mage-init' => ['buttonAdapter' => ['actions' => [[
                    'targetName' => $this->form . '.' . $this->form,
                    'actionName' => 'save',
                    'params' => [true, ['back' => 'continue']],
                ]]]]],
                'sort_order' => 80,
            ],
            default => [
                'label' => __('Save'),
                'class' => 'save primary',
                'data_attribute' => ['mage-init' => ['buttonAdapter' => ['actions' => [[
                    'targetName' => $this->form . '.' . $this->form,
                    'actionName' => 'save',
                    'params' => [false],
                ]]]]],
                'sort_order' => 90,
            ],
        };
    }
}
