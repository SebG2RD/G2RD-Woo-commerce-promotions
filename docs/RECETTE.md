# G2RD WooCommerce Promotions — guide de recette 1.0.0

## Installation

Utiliser un environnement de test avec les mêmes versions de WordPress, WooCommerce, thème, Wholesale, facturation et cache que Drakkar. Faire une sauvegarde avant activation. Aucun changement de thème ni de cœur WooCommerce. Le plugin se trouve dans WooCommerce > G2RD Promotions et exige `manage_woocommerce`.

1. Paramètres : choisir la vraie taxonomie de labels. `product_brand` par défaut, mais la boutique peut employer une taxonomie différente. Les IDs ne sont jamais réinterprétés lors d'un changement de taxonomie.
2. Sélectionner les exclusions globales, dont Firm Price si son sens métier est confirmé. La sélection de sous-catégories est héritée par les descendants.
3. Mapper les rôles Wholesale réels. Ne pas activer les campagnes B2B tant que la source des tarifs n'est pas confirmée. Le mode initial est désactivé. Vérifier les produits simples et chaque variation.
4. Ajouter une campagne en brouillon. Définir les profils, les règles (pourcentage, montant déduit ou prix fixe ; labels, catégories ou articles), le traitement des articles déjà en promotion, le début et la fin. Le champ fin représente l'instant exclusif : pour couvrir tout le 30 novembre, utiliser le 1er décembre à 00:00. Les dates utilisent le fuseau WordPress et sont stockées en UTC.
5. Prévisualiser. Les résultats sont limités à 20 produits parents par page et 60 combinaisons affichées, avec la règle appliquée. « Compter les articles par règle » parcourt tout le catalogue par lots de 100 et donne le nombre exact d'articles par règle (premier profil ciblé). La simulation porte sur la campagne isolée, pas les autres campagnes ni les overrides de prix externes.
6. Activer sur la recette. Exécuter la matrice ci-dessous. Vérifier également la conformité du prix de référence avant toute campagne destinée aux consommateurs.

## Règles retenues

- Une campagne contient une ou plusieurs règles. Dans une règle, les termes sélectionnés sont reliés par OU ; entre labels et catégories, par ET. Une sélection vide vaut « tous » ; une règle sans label ni catégorie est la règle générale, articles sans label compris. Des articles spécifiques (IDs) remplacent labels et catégories.
- Types de remise : pourcentage, montant déduit ou prix fixe, toujours calculés sur le prix normal (Regular Price). Un prix fixe supérieur au prix normal est ignoré.
- Une seule règle par article. Ordre : priorité manuelle (1 = la plus haute), puis articles spécifiques, label + catégorie, label ou catégorie, règle générale ; à égalité, l'ordre de la liste.
- Les visiteurs ne sont pas obligés de se connecter pour obtenir le prix public. Les trois profils sont indépendants ; une campagne publique cible normalement visiteurs + particuliers connectés.
- Si plusieurs campagnes simultanées correspondent, le prix le plus bas est appliqué ; à égalité, la plus ancienne (ID inférieur). Pas de cumul.
- Articles déjà en promotion (prix promo WooCommerce actif, dates comprises), au choix par campagne : **Exclure** (ils gardent leur prix promo, prioritaire sur toutes les règles) ou **Inclure** (le prix de la campagne remplace temporairement leur prix promo, même s'il est plus élevé, et l'ancien revient à la fin). Une promotion professionnelle détectée par clé ou filtre reste toujours prioritaire. Un prix Wholesale permanent n'est pas une promotion.
- Les exclusions produit couvrent les variations d'un parent. Une exclusion de variation ne concerne que cette variation.
- Le prix de base et le prix de vente natifs ne sont pas modifiés en base. Les filtres n'interviennent pas sur les écrans d'édition ordinaires, cron ou REST d'administration.
- Codes promo : un coupon marqué « Utilisation individuelle uniquement » est refusé dès que le panier contient un article en promotion. Les autres coupons (ex. coupon de remboursement d'un client sans compte) restent utilisables pendant la campagne. Un coupon réglé sur « Exclure les articles en promo » ignore déjà les articles de campagne.
- Au panier, le prix est recalculé côté serveur ; une commande enregistrée conserve son prix. La remise G2RD est déjà intégrée au sous-total WooCommerce : la ligne « Économie G2RD » est informative. Les coupons autorisés restent des remises WooCommerce séparées.
- La liste des produits en promotion (section « Discount », blocs et shortcodes « On sale ») est calculée en arrière-plan par Action Scheduler, par lots de 200 produits, au début et à la fin de chaque campagne et après chaque modification de produit. Pendant les premières minutes d'une campagne, seuls les articles en promo WooCommerce native y figurent.
- Le cache des pages publiques est désactivé pendant les campagnes actives si le mode sécurisé est coché, y compris la page d'accueil. Les caches sont purgés au début et à la fin. Ce choix est volontairement conservateur et coûte en performance. Désactiver la mise en cache des comptes Wholesale et purger également tout cache CDN/reverse proxy. Les caches externes ne sont pas contrôlés par le plugin.

## Adaptateur Wholesale

Le mode « Métadonnée produit par rôle » attend un prix unitaire réel dans la même devise et la même convention de TVA que WooCommerce. Exemple à confirmer : `{role}_wholesale_price`. Renseigner la clé de promotion Wholesale si elle existe. Sans prix numérique positif reconnu, la campagne B2B ne s'applique pas. Ne jamais activer ce mode si les prix sont dépendants de la quantité, d'un groupe, d'une devise ou de l'utilisateur et que les clés ne reflètent pas le tarif effectif.

Pour une extension tierce, utiliser un mini-plugin d'intégration séparé et sélectionner « Adaptateur PHP (filtre) ». Brancher le filtre sur l'API officielle de l'extension Wholesale installée. Exemple de structure, **pas** un adaptateur Drakkar opérationnel :

```php
add_filter('g2rd_promotions_wholesale_base_price', function ($price, $product, $role) {
    // Retourner le prix professionnel réel fourni par le plugin installé.
    // Retourner null si aucune source fiable. Ne pas appeler get_price() ici.
    return $price;
}, 10, 3);
add_filter('g2rd_promotions_has_wholesale_sale', function ($has_sale, $product, $role) {
    // Retourner true si une promotion Wholesale existe pour cette variation.
    return $has_sale;
}, 10, 3);
```

Le filtre `g2rd_promotions_product_eligible` reçoit `(bool, WC_Product, contexte, campagne)` pour des exclusions métier supplémentaires. `g2rd_promotions_frontend_ajax` autorise explicitement les actions AJAX de recherche front-office des administrateurs si le thème en utilise. Toute adaptation doit être testée avant mise en production.

## Facturation et paiements

Le moteur n'appelle aucune API de paiement et ne modifie ni les taxes, ni les frais de port, ni les transactions : WooCommerce calcule le total à régler. Le snapshot `_g2rd_promotion_snapshot` de la ligne de commande comprend ID/nom campagne, profil/rôle, taux, prix de base et prix final, quantité, devise, économie HT/TTC. Les métadonnées publiques `Promotion G2RD` et `Économie G2RD HT` sont disponibles pour les factures et emails. La ligne de total informative est ajoutée via `woocommerce_get_order_item_totals` et l'adaptateur de totaux WP Overnight. Pour un modèle WP Overnight personnalisé qui n'affiche pas la ligne, activer le résumé après le tableau uniquement si elle est absente :

```php
add_filter('g2rd_promotions_pdf_summary', '__return_true');
```

Les totaux ne doivent jamais soustraire deux fois l'économie. Tester le modèle de facture réellement utilisé. Les remboursements passent par WooCommerce sur les montants enregistrés. Le rapport statistique retire les remboursements de ligne et estime la part d'économie annulée au prorata des quantités ; les remboursements libres non associés à une ligne ne sont pas répartis par campagne. Maximum 5 000 commandes par rapport (réduire la période en cas de troncature).

## Matrice de recette obligatoire

| Scénario | Attendu |
|---|---|
| Visiteur anonyme, vinyle Drakkar 30 € à -30 % | 21 €, badge natif, prix barré, panier 21 € |
| Particulier connecté, même produit | Même prix si ciblé |
| Wholesale, tarif 18 €, campagne B2B -10 % | 16,20 € à la place de 18 €, aucun prix public repris |
| Wholesale non ciblé | Tarif professionnel initial |
| Produit à 25 € avec prix promo 18 €, mode Exclure | 18 €, campagne ignorée |
| Même produit, mode Inclure, règle prix fixe 20 € | 20 € pendant la campagne, 18 € après |
| Produit avec promo native programmée plus tard | Campagne appliquée jusqu'au début de la promo native (mode Exclure) |
| Exemple Black Friday du cahier des charges (7 règles) | Season of Mist −5 %, Osmose Pins & Patches −10 %, autres Osmose −30 %, produit sans label −40 % |
| Priorité manuelle 1 sur la règle générale | La règle générale l'emporte sur les règles de label |
| Montant déduit 5 € sur un article à 12 € | 7 € |
| Variation de T-shirt déjà soldée | Conservation de son prix, autres variations éligibles traitées séparément |
| Catégorie ou label non ciblé | Tarif d'origine |
| Firm Price exclu globalement | Tarif d'origine |
| Deux campagnes simultanées 20 % et 30 % | 30 % sans cumul |
| Activation / fin programmée, navigateur déjà connecté | Mise à jour du prix et du badge au bon instant |
| Panier ouvert avant activation / fin | Recalcul, prix correct avant paiement |
| Déconnexion et connexion B2B | Recalcul selon le nouveau profil, pas de fuite de prix |
| Produit ajouté ou reclassé pendant une campagne | Éligibilité immédiate après purge des caches nécessaires |
| Catalogue / page d'accueil / recherche / wishlist | Prix et badge conformes au thème |
| On sale WooCommerce / blocs et AJAX / section Discount | Produit éligible inclus quelques minutes après le début (index Action Scheduler) ; produits non éligibles absents hors promo native |
| Tri par prix / filtre min-max | **Limitation connue** : tables lookup non réindexées, corriger ou désactiver ce filtre avant production si nécessaire |
| Panier et checkout classique | Sous-total, TVA, livraison et total corrects |
| Panier et checkout blocs / Store API | Montant correct, métadonnées de commande |
| Coupon « Utilisation individuelle uniquement » + article en promo | Refus ; aucun cumul |
| Coupon cumulable (remboursement), visiteur sans compte | Accepté : remise G2RD une fois, coupon séparé |
| Paiement PayPal / WooPayments / express (sandbox) | Montant débité égal au total WooCommerce |
| Emails / facture PDF | Campagne, pourcentage, économie et total corrects |
| Commande existante après expiration/suppression | Prix et snapshot inchangés |
| Remboursement partiel / total | Remboursement basé sur le montant payé, statistiques cohérentes |
| WP Rocket et cache CDN, deux navigateurs et rôles | Aucun prix Wholesale visible publiquement, aucun prix expiré |
| Permissions / nonce | Rôle sans manage_woocommerce refusé, requêtes sans nonce refusées |
| Erreur d'installation des tables | Moteur désactivé, avis administrateur |
| Prix de référence B2C sur 30 jours | Vérification externe indispensable : non implémenté dans cette version |

## Vérifications effectuées hors WordPress

`php tests/run.php` : 83 assertions sur des simulations des APIs WordPress et WooCommerce. `php -l` sur chaque fichier PHP et `node --check assets/admin.js`. Aucune installation WordPress, base WooCommerce, passerelle de paiement ou extension Wholesale réelle n'est disponible dans l'environnement de génération. Ces tests ne constituent pas une homologation de production.

## Maintenance

Les tables sont préfixées selon le site : `g2rd_promotions`, `g2rd_promotion_logs`. Les règles sont en JSON dans la première table. Les snapshots de commande utilisent uniquement les CRUD WooCommerce (compatible par conception avec HPOS ; test d'intégration requis). Les campagnes sont évaluées par horloge serveur même si le planificateur est en retard. Action Scheduler purge les caches aux bornes et vérifie les transitions toutes les cinq minutes ; WP-Cron est utilisé en secours. Configurer un cron serveur fiable.

La désactivation retire les filtres et conserve les données. La désinstallation conserve également les campagnes et les journaux par défaut ; définir explicitement `G2RD_PROMOTIONS_DELETE_DATA` à `true` avant désinstallation pour supprimer les tables et réglages. Les anciennes commandes conservent toujours leurs montants et leurs métadonnées.

L'interface autonome reprend les cartes, l'accent WordPress et les icônes linéaires de G2RD Connector, sans dépendance ni compilation. Référence : https://github.com/SebG2RD/g2rd-connector/blob/main/assets/admin/src/style.css. G2RD Facturation n'était pas accessible pour comparaison. La console native du thème, le calendrier graphique, les graphiques avancés, la traduction complète EN et l'historique réglementaire des prix restent à développer.
