<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Content;

/**
 * Balisage schema.org « FAQPage » (JSON-LD) des questions affichées.
 *
 * Une page ne doit porter qu'un seul FAQPage : claim() n'accorde le droit de l'écrire qu'au premier bloc rendu pendant
 * la requête (une page peut afficher plusieurs blocs FAQ, par exemple deux widgets).
 */
class JsonLd
{
    private bool $claimed = false;

    /**
     * @return bool Vrai pour le premier appel de la requête seulement.
     */
    public function claim(): bool
    {
        if ($this->claimed) {
            return false;
        }
        $this->claimed = true;

        return true;
    }

    /**
     * @param array<int, array{question:string,answer:string}> $items Questions (texte brut) et réponses (HTML sûr).
     * @return string JSON, vide s'il n'y a rien à décrire. Sûr à placer dans une balise script de type JSON-LD :
     *                les signes < > & ' " sont écrits en séquences \u.
     */
    public function build(array $items): string
    {
        $entities = [];
        foreach ($items as $item) {
            $question = trim((string) preg_replace('/\s+/u', ' ', strip_tags($item['question'])));
            $answer = trim($item['answer']);
            if ($question === '' || $answer === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }
        if (!$entities) {
            return '';
        }
        $json = json_encode(
            ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return $json === false ? '' : $json;
    }
}
