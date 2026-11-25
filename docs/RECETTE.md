# G2RD WooCommerce Promotions — guide de recette 1.0.0

## Installation

Utiliser un environnement de test avec les mêmes versions de WordPress, WooCommerce, thème, Wholesale, facturation et cache que Drakkar. Faire une sauvegarde avant activation. Aucun changement de thème ni de cœur WooCommerce. Le plugin se trouve dans WooCommerce > G2RD Promotions et exige `manage_woocommerce`.

1. Paramètres : choisir la vraie taxonomie de labels. `product_brand` par défaut, mais la boutique peut employer une taxonomie différente. Les IDs ne sont jamais réinterprétés lors d'un changement de taxonomie.
2. Sélectionner les exclusions globales, dont Firm Price si son sens métier est confirmé. La sélection de sous-catégories est héritée par les descendants.
3. Mapper les rôles Wholesale réels. Ne pas activer les campagnes B2B tant que la source des tarifs n'est pas confirmée. Le mode initial est désactivé. Vérifier les produits simples et chaque variation.
4. Ajouter une campagne en brouillon. Définir les profils, labels, catégories, pourcentage, début et fin. Le champ fin représente l'instant exclusif : pour couvrir tout le 30 novembre, utiliser le 1er décembre à 00:00. Les dates utilisent le fuseau WordPress et sont stockées en UTC.
5. Prévisualiser. Les résultats sont limités à 20 produits parents par page et 60 combinaisons affichées. Le nombre de candidats inclut les exclusions et n'est pas le nombre exact de produits éligibles. La simulation porte sur la campagne isolée, pas les autres campagnes ni les overrides de prix externes.
6. Activer sur la recette. Exécuter la matrice ci-dessous. Vérifier également la conformité du prix de référence avant toute campagne destinée aux consommateurs.

## Règles retenues

- Une campagne = une réduction. Dans une dimension, les termes sélectionnés sont reliés par OU ; entre labels et catégories, par ET. « Tous » doit être choisi explicitement.
- Les visiteurs ne sont pas obligés de se connecter pour obtenir le prix public. Les trois profils sont indépendants ; une campagne publique cible normalement visiteurs + particuliers connectés.
- Si plusieurs campagnes correspondent, la plus forte remise est appliquée ; à égalité, la plus ancienne (ID inférieur). Pas de cumul.
- Les promotions WooCommerce individuelles (même programmées) restent intactes. Une promotion professionnelle détectée par clé ou filtre reste prioritaire. Un prix Wholesale permanent n'est pas une promotion.
- Les exclusions produit couvrent les variations d'un parent. Une exclusion de variation ne concerne que cette variation.
- Le prix de base et le prix de vente natifs ne sont pas modifiés en base. Les filtres n'interviennent pas sur les écrans d'édition ordinaires, cron ou REST d'administration.
- Un code promotionnel est interdit pour **tout le panier** si une campagne présente n'autorise pas le cumul. Cette politique conservatrice évite un double avantage sur les coupons fixes du panier. La campagne offre un réglage explicite pour l'autoriser.
- Au panier, le prix est recalculé côté serveur ; une commande enregistrée conserve son prix. La remise G2RD est déjà intégrée au sous-total WooCommerce : la ligne « Économie G2RD » est informative. Les coupons autorisés restent des remises WooCommerce séparées.
- Le cache des pages publiques est désactivé pendant les campagnes actives ou programmées si le mode sécurisé est coché, y compris la page d'accueil. Ce choix est volontairement conservateur et coûte en performance. Désactiver la mise en cache des comptes Wholesale et purger également tout cache CDN/reverse proxy. Les caches externes ne sont pas contrôlés par le plugin.

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
| Produit à 25 € avec prix promo 18 € | 18 €, campagne ignorée |
| Variation de T-shirt déjà soldée | Conservation de son prix, autres variations éligibles traitées séparément |
| Catégorie ou label non ciblé | Tarif d'origine |
| Firm Price exclu globalement | Tarif d'origine |
| Deux campagnes 20 % et 30 % | 30 % sans cumul |
| Activation / fin programmée, navigateur déjà connecté | Mise à jour du prix et du badge au bon instant |
| Panier ouvert avant activation / fin | Recalcul, prix correct avant paiement |
| Déconnexion et connexion B2B | Recalcul selon le nouveau profil, pas de fuite de prix |
| Produit ajouté ou reclassé pendant une campagne | Éligibilité immédiate après purge des caches nécessaires |
| Catalogue / page d'accueil / recherche / wishlist | Prix et badge conformes au thème |
| On sale WooCommerce / blocs et AJAX | Produit éligible inclus ; produits non éligibles absents hors promo native |
| Tri par prix / filtre min-max | **Limitation connue** : tables lookup non réindexées, corriger ou désactiver ce filtre avant production si nécessaire |
| Panier et checkout classique | Sous-total, TVA, livraison et total corrects |
| Panier et checkout blocs / Store API | Montant correct, métadonnées de commande |
| Coupon incompatible | Refus ; aucun cumul |
| Coupon autorisé | Remise G2RD une fois, coupon séparé |
| Paiement PayPal / WooPayments / express (sandbox) | Montant débité égal au total WooCommerce |
| Emails / facture PDF | Campagne, pourcentage, économie et total corrects |
| Commande existante après expiration/suppression | Prix et snapshot inchangés |
| Remboursement partiel / total | Remboursement basé sur le montant payé, statistiques cohérentes |
| WP Rocket et cache CDN, deux navigateurs et rôles | Aucun prix Wholesale visible publiquement, aucun prix expiré |
| Permissions / nonce | Rôle sans manage_woocommerce refusé, requêtes sans nonce refusées |
| Erreur d'installation des tables | Moteur désactivé, avis administrateur |
| Prix de référence B2C sur 30 jours | Vérification externe indispensable : non implémenté dans cette version |

## Vérifications effectuées hors WordPress

`php tests/run.php` : 51 assertions sur des simulations des APIs WordPress et WooCommerce. `php -l` sur chaque fichier PHP et `node --check assets/admin.js`. Aucune installation WordPress, base WooCommerce, passerelle de paiement ou extension Wholesale réelle n'est disponible dans l'environnement de génération. Ces tests ne constituent pas une homologation de production.

## Maintenance

Les tables sont préfixées selon le site : `g2rd_promotions`, `g2rd_promotion_logs`. Les règles sont en JSON dans la première table. Les snapshots de commande utilisent uniquement les CRUD WooCommerce (compatible par conception avec HPOS ; test d'intégration requis). Les campagnes sont évaluées par horloge serveur même si le planificateur est en retard. Action Scheduler purge les caches aux bornes et vérifie les transitions toutes les cinq minutes ; WP-Cron est utilisé en secours. Configurer un cron serveur fiable.

La désactivation retire les filtres et conserve les données. La désinstallation conserve également les campagnes et les journaux par défaut ; définir explicitement `G2RD_PROMOTIONS_DELETE_DATA` à `true` avant désinstallation pour supprimer les tables et réglages. Les anciennes commandes conservent toujours leurs montants et leurs métadonnées.

L'interface autonome reprend les cartes, l'accent WordPress et les icônes linéaires de G2RD Connector, sans dépendance ni compilation. Référence : https://github.com/SebG2RD/g2rd-connector/blob/main/assets/admin/src/style.css. G2RD Facturation n'était pas accessible pour comparaison. La console native du thème, le calendrier graphique, les graphiques avancés, la traduction complète EN et l'historique réglementaire des prix restent à développer.
