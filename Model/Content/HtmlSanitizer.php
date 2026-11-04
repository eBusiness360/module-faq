<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Content;

use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Nettoie le HTML d'une réponse par liste blanche : une réponse affichée ne peut jamais exécuter de script.
 *
 * - balises autorisées : mise en forme simple (paragraphes, listes, gras, italique, liens, images, tableaux, titres de
 *   niveau 3 à 6...) ; toute autre balise est retirée mais son texte est gardé ;
 * - balises retirées avec leur contenu : script, style, iframe, objet, formulaire...
 * - attributs : seuls href/title/target/rel (liens), src/alt/title/width/height (images) et quelques attributs de
 *   tableau survivent ; aucun gestionnaire d'événement, aucun style ; les adresses ne peuvent être que http, https,
 *   mailto, tel ou relatives (jamais « javascript: » ni « data: »).
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sub',
        'sup', 'a', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre', 'span', 'div', 'img', 'table', 'thead',
        'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    private const DROPPED_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'noscript', 'template',
        'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'head',
        'title', 'audio', 'video', 'source', 'canvas', 'dialog',
    ];

    private const ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];
    private const IMAGE_SCHEMES = ['http', 'https'];

    /**
     * @param string $html HTML saisi dans l'administration.
     * @return string HTML sûr.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // On parcourt tout le corps : une balise fermante en trop dans le contenu (« a</div>b ») ne doit pas faire
        // perdre ce qui la suit.
        $root = $document->getElementsByTagName('body')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }
        $this->clean($root);
        $output = '';
        foreach ($root->childNodes as $node) {
            $output .= $document->saveHTML($node);
        }

        return trim($output);
    }

    /**
     * @param DOMNode $parent Nœud dont on nettoie les enfants.
     * @return void
     */
    private function clean(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMCdataSection || !($child instanceof DOMText || $child instanceof DOMElement)) {
                $parent->removeChild($child);
                continue;
            }
            if ($child instanceof DOMText) {
                continue;
            }
            $name = strtolower($child->nodeName);
            if (in_array($name, self::DROPPED_WITH_CONTENT, true)) {
                $parent->removeChild($child);
                continue;
            }
            $this->clean($child);
            if (!in_array($name, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }
            $this->cleanAttributes($child, $name);
            // Un lien sans adresse (adresse refusée, ou cible supprimée) n'est plus un lien : on garde son contenu.
            if ($name === 'a' && !$child->hasAttribute('href') && $child->parentNode !== null) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
            }
        }
    }

    /**
     * @param DOMElement $element Élément autorisé.
     * @param string     $name    Nom de la balise.
     * @return void
     */
    private function cleanAttributes(DOMElement $element, string $name): void
    {
        $allowed = self::ATTRIBUTES[$name] ?? [];
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (!in_array(strtolower($attribute->nodeName), $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
        if ($name === 'a') {
            $this->cleanLink($element);
        } elseif ($name === 'img') {
            $src = $this->safeUrl($element->getAttribute('src'), self::IMAGE_SCHEMES);
            if ($src === null) {
                $element->parentNode?->removeChild($element);

                return;
            }
            $element->setAttribute('src', $src);
            $element->setAttribute('loading', 'lazy');
            foreach (['width', 'height'] as $dimension) {
                if ($element->hasAttribute($dimension) && !ctype_digit($element->getAttribute($dimension))) {
                    $element->removeAttribute($dimension);
                }
            }
        }
    }

    /**
     * @param DOMElement $link Lien.
     * @return void
     */
    private function cleanLink(DOMElement $link): void
    {
        if ($link->hasAttribute('href')) {
            $href = $this->safeUrl($link->getAttribute('href'), self::LINK_SCHEMES);
            if ($href === null) {
                $link->removeAttribute('href');
            } else {
                $link->setAttribute('href', $href);
            }
        }
        $target = strtolower($link->getAttribute('target'));
        if ($target === '_blank') {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
        } else {
            $link->removeAttribute('target');
            $link->removeAttribute('rel');
        }
    }

    /**
     * @param string   $url     Adresse saisie.
     * @param string[] $schemes Schémas permis.
     * @return string|null Adresse ou null si elle est refusée.
     */
    private function safeUrl(string $url, array $schemes): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        // Les navigateurs ignorent les espaces et caractères de contrôle dans le schéma (« java\tscript: »).
        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', $url) ?? '';
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $match) === 1) {
            return in_array(strtolower($match[1]), $schemes, true) ? $url : null;
        }

        return $url;
    }
}
