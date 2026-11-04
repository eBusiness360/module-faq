<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Content;

use Magento\Cms\Model\Template\FilterProvider;

/**
 * Prépare une réponse pour l'affichage : directives de contenu de Magento limitées à une liste blanche, puis HTML
 * nettoyé (HtmlSanitizer).
 *
 * Les réponses importées de Mageprince contiennent des liens écrits en directives Magento (« {{widget type=... }} » vers
 * une catégorie, une page CMS ou un produit) ; ceux-là sont gardés, ainsi que « {{media url=...}} » et
 * « {{store url=...}} ». Toute autre directive (bloc, variable, configuration, condition...) est supprimée : le texte
 * d'une réponse ne peut ni lire la configuration ni afficher un bloc. Le HTML produit est ensuite nettoyé, de sorte
 * qu'aucun script ne survit, quelle que soit l'origine.
 */
class AnswerRenderer
{
    /** Types de widget permis dans une réponse (liens seulement), en minuscules. */
    public const ALLOWED_WIDGETS = [
        'magento\\catalog\\block\\category\\widget\\link',
        'magento\\catalog\\block\\product\\widget\\link',
        'magento\\cms\\block\\widget\\page\\link',
    ];

    /** Paramètres permis dans ces widgets de lien. */
    public const ALLOWED_PARAMS = ['type', 'id_path', 'anchor_text', 'title', 'page_id', 'template'];

    /** Seuls gabarits permis : ceux, en ligne ou en bloc, des widgets de lien de Magento. */
    public const ALLOWED_TEMPLATES = [
        'category/widget/link/link_inline.phtml',
        'category/widget/link/link_block.phtml',
        'product/widget/link/link_inline.phtml',
        'product/widget/link/link_block.phtml',
        'widget/link/link_inline.phtml',
        'widget/link/link_block.phtml',
    ];

    /**
     * @param FilterProvider $filterProvider Filtre de contenu CMS de Magento.
     * @param HtmlSanitizer  $sanitizer      Nettoyage du HTML.
     */
    public function __construct(
        private readonly FilterProvider $filterProvider,
        private readonly HtmlSanitizer $sanitizer
    ) {
    }

    /**
     * @param string $answer  Réponse telle qu'enregistrée.
     * @param int    $storeId Vue de magasin (adresses des liens).
     * @return string HTML sûr.
     */
    public function render(string $answer, int $storeId): string
    {
        $answer = $this->keepAllowedDirectives($this->flattenPageBuilder($answer));
        if (str_contains($answer, '{{')) {
            try {
                $answer = (string) $this->filterProvider->getBlockFilter()->setStoreId($storeId)->filter($answer);
            } catch (\Throwable) {
                $answer = (string) preg_replace('~\{\{.*?\}\}~s', '', $answer);
            }
        }

        return $this->sanitizer->sanitize($answer);
    }

    /**
     * Les réponses écrites avec Magezon Page Builder (« [mgz_pagebuilder]{json}[/mgz_pagebuilder] ») sont ramenées à
     * leurs textes : une FAQ n'a pas besoin de mise en page, et ce balisage n'a de sens que pour Magezon.
     *
     * @param string $html Réponse brute.
     * @return string
     */
    public function flattenPageBuilder(string $html): string
    {
        if (!str_contains($html, '[mgz_pagebuilder]')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '~\[mgz_pagebuilder\](.*?)\[/mgz_pagebuilder\]~s',
            function (array $match): string {
                $data = json_decode(trim($match[1]), true);
                $parts = [];
                $walk = function (mixed $nodes) use (&$walk, &$parts): void {
                    if (!is_array($nodes)) {
                        return;
                    }
                    foreach ($nodes as $node) {
                        if (!is_array($node)) {
                            continue;
                        }
                        if (($node['type'] ?? '') === 'text' && is_string($node['content'] ?? null)) {
                            $parts[] = $node['content'];
                        }
                        $walk($node['elements'] ?? null);
                    }
                };
                $walk(is_array($data) ? ($data['elements'] ?? null) : null);

                return implode("\n", $parts);
            },
            $html
        );
    }

    /**
     * @param string $html Réponse brute.
     * @return string La réponse sans les directives hors liste blanche.
     */
    public function keepAllowedDirectives(string $html): string
    {
        if (!str_contains($html, '{{')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '~\{\{.*?\}\}~s',
            function (array $match): string {
                // Les directives enregistrées par l'éditeur ont parfois des guillemets échappés (\") et des barres
                // doublées : le filtre de Magento en ferait des adresses fausses. On les remet en forme normale.
                $directive = str_replace(['\\"', '\\\\'], ['"', '\\'], $match[0]);

                return $this->isAllowedDirective($directive) ? $directive : '';
            },
            $html
        );
    }

    /**
     * @param string $directive Directive complète, accolades comprises.
     * @return bool
     */
    private function isAllowedDirective(string $directive): bool
    {
        if (preg_match('~^\{\{\s*(media|store)\s+url\s*=\s*"[^"{}]*"\s*\}\}$~i', $directive) === 1) {
            return true;
        }
        // Forme stricte : « {{widget nom="valeur" nom="valeur"... }} », guillemets doubles seulement, valeurs sans
        // guillemet ni accolade. Aucune ambiguïté possible entre ce contrôle et l'analyse du filtre de Magento.
        if (preg_match('~^\{\{widget((?:\s+[a-z_]+="[^"{}]*")+)\s*\}\}$~', $directive, $match) !== 1) {
            return false;
        }
        if (preg_match_all('~\s([a-z_]+)="([^"]*)"~', $match[1], $pairs, PREG_SET_ORDER) < 1) {
            return false;
        }
        $params = [];
        foreach ($pairs as [, $name, $value]) {
            // Un paramètre en double ou hors liste est refusé : c'est ce qui permettait de faire passer un second
            // « type » ou un gabarit au filtre.
            if (isset($params[$name]) || !in_array($name, self::ALLOWED_PARAMS, true)) {
                return false;
            }
            $params[$name] = $value;
        }
        // Magento écrit les barres inverses simples ou doublées selon l'éditeur.
        $type = strtolower(str_replace('\\\\', '\\', $params['type'] ?? ''));
        if (!in_array($type, self::ALLOWED_WIDGETS, true)) {
            return false;
        }

        return !isset($params['template']) || in_array($params['template'], self::ALLOWED_TEMPLATES, true);
    }
}
