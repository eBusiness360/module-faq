# Changelog — Maxcode_Faq

Toutes les évolutions notables du module sont consignées ici.
Format : [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/), versionnage [SemVer](https://semver.org/lang/fr/).

## [1.2.1] — 2026-11-20

### Modifié
- Documentation : badge de version Packagist dans le README. Aucun changement de code.

## [1.2.0] - 2026-11-19

### Ajouté
- Des variables CSS pour les couleurs qui étaient en dur : `--mcfaq-icon-color`, `--mcfaq-icon-border`, `--mcfaq-icon-bg`, `--mcfaq-icon-hover-bg`, `--mcfaq-icon-open-bg`, `--mcfaq-icon-open-color` (le bouton rond « + » / « × » des questions) et `--mcfaq-on-accent`, `--mcfaq-on-strong` (le blanc posé sur l'accent ou sur la couleur forte). Valeurs par défaut : celles d'avant, rien ne change sans réglage ; un thème n'a plus qu'à surcharger les variables (documentées dans le README).

## [1.1.1] - 2026-11-19

### Corrigé
- Les grilles de l'administration (commandes, clients, pages CMS…) ne s'affichaient plus quand le module était activé : « Not registered handle sales_order_grid_data_source ». Les sources des deux grilles de la FAQ étaient déclarées dans `etc/adminhtml/di.xml`, ce qui remplaçait celles de tous les autres modules ; elles sont maintenant déclarées dans `etc/di.xml`, et un test l'empêche de revenir.

## [1.1.0] - 2026-11-12

### Ajouté
- Le formulaire « nouvelle question » accepte une question de départ dans l'adresse (`question=`), par exemple depuis les recherches sans résultat de Maxcode Search Insights : la question arrive pré-remplie, en brouillon.

## [1.0.2] - 2026-11-05 — PHP 8.5

### Modifié
- La contrainte PHP accepte aussi la branche 8.5 (`~8.5.0`), la version de PHP prise en charge par les dernières versions de Magento ; la revue de la Marketplace Adobe l'exige.

## [1.0.1] - 2026-11-05 — Licence gratuite et contraintes Composer

### Corrigé
- `LICENSE` contenait le CLUF des modules payants : c'est désormais le CLUF des modules gratuits, comme l'annonce le README.

### Modifié
- Les dépendances `magento/*` ont des plages de versions au lieu de `*`, comme l'exige la revue de la Marketplace Adobe.

## [1.0.0] - 2026-11-04 — Première version

Une FAQ unique pour la boutique : une seule à nourrir et à afficher, à la place de Mageprince FAQ et de MageWorx FAQ.

### Ajouté
- **Des groupes et des questions** (tables `maxcode_faq_*`, schéma déclaratif) : un texte par défaut et, au besoin, un texte par vue de magasin (question et réponse ensemble), des vues de magasin où la question est visible (toutes par défaut), des groupes de clients autorisés (aucun = tout le monde), un ou plusieurs groupes, un ordre de tri, un statut actif ou brouillon, et une origine (`source`, `source_ref`) pour les questions importées.
- **La page FAQ**, à l'adresse réglée par vue de magasin (routeur `Controller/Router`), avec titre, méta-titre, méta-description et adresse canonique ; questions classées par groupe, puis les questions sans groupe.
- **Le widget « FAQ (Maxcode) »** : un ou plusieurs groupes, des numéros de questions, un titre, les titres de groupe, le bouton « Tout ouvrir », un nombre maximal de questions ; utilisable dans toute page ou tout bloc CMS (par exemple une page contact).
- **Des blocs sur les fiches produit, les catégories et les pages CMS** (accueil compris), alimentés par les affectations de chaque question (catégories, pages CMS, produits par SKU), activables séparément.
- **Un accordéon accessible sans JavaScript** (`details` / `summary`) : titre de groupe en majuscules à gauche, liste à droite (filet épais, filets fins, bouton rond « + » / « × »), titre au-dessus sur écran étroit ; style par variables CSS (`--mcfaq-accent` reprend `--primary` du thème, `--mcfaq-strong`) ; ajoutés par JavaScript (script posé par le rendu sécurisé de Magento, compatible avec la politique CSP) : des **filtres par groupe en pastilles avec compteurs** (réglage « Filtrer par groupe » et paramètre `group_tabs` du widget), le bouton « Tout ouvrir / Tout fermer » et l'ouverture d'une question depuis l'ancre `#mcfaq-q<n>`.
- **Un contraste soigné (WCAG 2.2)** qui ne dépend pas du fond de la page : surface blanche pour la liste, pastilles et boutons à bordure franche (au moins 4:1), textes à 9,8:1 au moins (questions 18,1:1), titres de groupe 12,2:1, anneau de focus visible (6,7:1), couleurs réglables par variables (`--mcfaq-accent`, `--mcfaq-heading`, `--mcfaq-strong`, `--mcfaq-surface`), mouvement réduit et mode « couleurs forcées » respectés.
- **Le balisage FAQPage (JSON-LD)**, réglable, écrit une seule fois par page, sans doublon de question, sans possibilité de sortir de la balise script. La documentation ne promet aucun résultat enrichi.
- **Des réponses sûres** : liste blanche de balises et d'attributs (`HtmlSanitizer`), adresses limitées à http, https, mailto, tel ou relatives ; directives de contenu de Magento limitées aux liens vers une catégorie, un produit ou une page CMS et à `{{media}}` / `{{store}}` (les guillemets échappés et barres doublées de l'éditeur sont normalisés) ; le balisage Magezon Page Builder est réduit à ses textes.
- **Une administration standard** : grilles et formulaires en composants d'interface (Contenu > FAQ > Questions et Groupes), actions de masse (supprimer, publier, remettre en brouillon), éditeur de réponse, traductions par vue de magasin, affectations ; droits d'accès propres (`Maxcode_Faq::questions`, `::groups`, `::config`) ; réglages sous Stores > Configuration > Maxcode > FAQ (section rattachée à l'onglet Maxcode, déclaré aussi par chacun des modules voisins).
- **L'import** `bin/magento maxcode:faq:import` (`--source`, `--dry-run`, `--convert-widgets`) depuis Mageprince FAQ (`prince_faq`, `prince_faqgroup`) et MageWorx FAQ (`mageworx_faq_*`) : lecture seule des tables sources (aucune dépendance de code), conversion pure et testée, écriture dans une transaction, relançable sans doublon (une question déjà importée n'est jamais écrasée), questions dont les vues de magasin ou les groupes de clients n'existent pas sur le site importées en brouillon, rapport d'avertissements ; réécriture optionnelle du widget Mageprince dans les pages et blocs CMS.
- **Traduction française** complète (`i18n/fr_FR.csv`), **tests unitaires** (`Test/Unit`).

### Sécurité et prudence
- L'import n'affiche jamais une question plus largement que la source : les restrictions (vues de magasin, groupes de clients) et l'état (actif) du GROUPE Mageprince s'ajoutent à celles de la question ; une question visible nulle part, ou dont les groupes ont des restrictions différentes, est importée en brouillon et signalée. Chez MageWorx, seuls un bloc actif et un élément actif comptent, et les blocs « spéciaux » ne sont pas publiés sur la page FAQ.
- Désactiver un groupe cache ses questions sur la page FAQ.
- Les directives de contenu autorisées dans une réponse suivent une forme stricte (guillemets doubles, paramètres en liste fermée, jamais deux fois le même, gabarits en liste fermée) : impossible de glisser un second `type` ou de faire afficher un bloc.
- Publier des questions en masse recharge chaque question : une question sans réponse reste en brouillon. Une traduction sans réponse retombe sur le texte par défaut.
- Le bloc d'une fiche produit ou d'une catégorie lit le produit ou la catégorie courants dans le registre (et non le paramètre `id` de l'adresse, qui est le numéro d'une ligne de panier sur la page de modification du panier).
- Cache de pages : enregistrer une question ne purge que la page FAQ, les widgets et les pages auxquelles elle est affectée (étiquettes par produit, catégorie ou page CMS, anciennes affectations comprises).
- L'adresse de la page FAQ est refusée si une page CMS ou une réécriture d'adresse la sert déjà (et si elle commence par un mot réservé de Magento).
- Une question sans ligne de vue de magasin n'est visible nulle part ; pas de clé étrangère vers `customer_group` (supprimer un groupe de clients ne rend pas publique une question qui lui était réservée).
- La réécriture des widgets Mageprince garde l'écriture de l'original (guillemets échappés dans un JSON Magezon) et signale les instances de widget et les widgets MageWorx qu'elle ne convertit pas.
- Les filtres par groupe cachent aussi les questions sans groupe ; la confirmation de suppression d'une grille échappe le texte de la question ; un texte comme « < 5 min » n'est plus tronqué ; ancres HTML uniques par page ; adresse `#` mal formée sans erreur.
