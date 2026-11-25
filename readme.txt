=== G2RD WooCommerce Promotions ===
Contributors: g2rd
Tags: woocommerce, promotion, wholesale, discount, brands
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Campagnes automatiques par label, catégorie, profil client et période. Version initiale à qualifier sur une boutique de recette.

== Description ==
* Plusieurs campagnes simultanées, remises en pourcentage, dates et heures, activation, duplication, suppression.
* Profils visiteurs, particuliers connectés et Wholesale. Source des prix professionnels à configurer explicitement.
* Exclusions par produit, label, catégorie. Les prix promo WooCommerce individuels restent protégés, même programmés.
* Prix dynamiques, badges natifs, variations, panier, commande et métadonnées de facture.
* Aperçu serveur paginé, tableau de bord, calendrier chronologique, statistiques, export CSV et journal.
* Action Scheduler / WP-Cron, purge WP Rocket, cache de variations séparé par profil.

== Installation ==
1. Sur une copie de recette, sauvegarder les fichiers et la base de données.
2. Extensions > Ajouter une extension > Téléverser une extension. Choisir le ZIP, installer et activer.
3. WooCommerce > G2RD Promotions > Paramètres. Configurer la taxonomie des labels, les rôles et la source Wholesale, les exclusions et les caches.
4. Ajouter une campagne en brouillon. Calculer la prévisualisation et vérifier le prix en boutique.
5. Activer et tester la chaîne complète en mode paiement test avant toute utilisation réelle.

== Limitations de cette version ==
Les tests exécutés sont des tests PHP autonomes sur des APIs simulées, pas des tests sur une installation WordPress/WooCommerce réelle. La compatibilité avec les extensions Wholesale, factures PDF, thème, caches et paiements express de Drakkar reste à valider. Les tarifs B2B dynamiques par quantité, groupe ou utilisateur exigent un adaptateur. Le tri et le filtre de prix reposant sur les tables de recherche WooCommerce ne sont pas adaptés aux tarifs dynamiques dans cette version. L'historique réglementaire des 30 jours n'est pas inclus. Interface française, sans traduction anglaise complète. Le mode natif intégré à la console du thème n'est pas implémenté : interface autonome inspirée du Connector.

== Changelog ==
= 1.0.0 =
* Première version installable, destinée à la recette.
* Chaîne CI/CD GitHub Actions (tests, contrôles, publication du ZIP).
