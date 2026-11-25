=== G2RD WooCommerce Promotions ===
Contributors: g2rd
Tags: woocommerce, promotion, wholesale, discount, brands
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Campagnes automatiques par label, catégorie, profil client et période. Version initiale à qualifier sur une boutique de recette.

== Description ==
* Plusieurs campagnes simultanées, plusieurs règles par campagne : pourcentage, montant déduit ou prix fixe ; dates et heures, activation, duplication, suppression.
* Règles par label, catégorie, combinaison des deux ou articles spécifiques, plus une règle générale. La règle la plus précise l'emporte, priorité manuelle possible, jamais de cumul.
* Profils visiteurs, particuliers connectés et Wholesale. Source des prix professionnels à configurer explicitement.
* Exclusions par produit, label, catégorie. Articles déjà en promotion exclus ou inclus au choix par campagne ; prix promo d'origine rétabli à la fin.
* Seuls les codes promo « Utilisation individuelle uniquement » sont refusés à côté d'un article en promotion.
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
= 1.1.0 =
* Plusieurs règles par campagne : pourcentage, montant déduit, prix fixe, articles spécifiques et règle générale.
* Priorité par spécificité et priorité manuelle, au lieu de la plus forte remise.
* Option « exclure / inclure les articles déjà en promotion » ; dates des promos WooCommerce respectées.
* Coupons : seuls les codes à usage individuel sont refusés à côté d'un article en promotion.
* Liste des produits en promotion calculée en arrière-plan ; cache coupé seulement pendant les campagnes actives.
* Prévisualisation : règle appliquée et comptage exact par règle. Campagnes 1.0.x reprises sans migration.

= 1.0.4 =
* Padding du bandeau admin : contenu décollé des bords.

= 1.0.3 =
* Version installable complète : updater GitHub + admin Verre + correctifs CI.
* À installer une fois manuellement sur les sites qui n’avaient pas encore les notifications de mise à jour.

= 1.0.2 =
* Interface admin direction Verre G2RD (bandeau, tuiles, polices).
* Onglets sans rechargement de page (navigation par hash).

= 1.0.1 =
* Notifications de mise à jour WordPress via les GitHub Releases (ZIP de release).

= 1.0.0 =
* Première version installable, destinée à la recette.
* Chaîne CI/CD GitHub Actions (tests, contrôles, publication du ZIP).
