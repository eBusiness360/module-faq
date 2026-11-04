<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Colonne d'actions d'une grille (Modifier, Supprimer). Déclarée en DI une fois par grille.
 */
class Actions extends Column
{
    /**
     * @param ContextInterface   $context            Contexte.
     * @param UiComponentFactory $uiComponentFactory Fabrique de composants.
     * @param UrlInterface       $urlBuilder         Constructeur d'adresses.
     * @param array              $components         Composants enfants.
     * @param array              $data               Données.
     * @param string             $route              Route du contrôleur (« maxcode_faq/question »).
     * @param string             $idField            Colonne d'identifiant.
     * @param string             $idParam            Paramètre d'identifiant dans l'adresse.
     * @param string             $labelField         Colonne qui nomme la ligne (message de confirmation).
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = [],
        private readonly string $route = '',
        private readonly string $idField = 'id',
        private readonly string $idParam = 'id',
        private readonly string $labelField = ''
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$this->idField])) {
                continue;
            }
            $params = [$this->idParam => $item[$this->idField]];
            $name = $this->getData('name');
            // La fenêtre de confirmation affiche ce message en HTML : le texte de la question y est échappé.
            $label = htmlspecialchars(
                mb_substr(trim((string) ($item[$this->labelField] ?? '')), 0, 80),
                ENT_QUOTES,
                'UTF-8'
            );
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl($this->route . '/edit', $params),
                'label' => __('Edit'),
            ];
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl($this->route . '/delete', $params),
                'label' => __('Delete'),
                'post' => true,
                'confirm' => [
                    'title' => __('Delete'),
                    'message' => __('Are you sure you want to delete "%1"?', $label),
                ],
            ];
        }

        return $dataSource;
    }
}
