# Changelog

## 1.1.0 — conformité au cahier des charges Drakkar

- Plusieurs règles par campagne ; types pourcentage, montant déduit et prix fixe, calculés sur le prix normal.
- Sélection d'articles spécifiques ; règle générale (sans label ni catégorie) pour tous les autres articles.
- Priorité par spécificité (articles > label + catégorie > label ou catégorie > générale) et priorité manuelle, au lieu de « la plus forte remise ».
- Option par campagne : exclure ou inclure les articles déjà en promotion. Les dates des promos WooCommerce sont respectées.
- Coupons : seuls les codes « Utilisation individuelle uniquement » sont refusés à côté d'un article en promotion ; l'interrupteur par campagne est supprimé.
- Liste « On sale » / section Discount calculée en arrière-plan (Action Scheduler) au lieu de parcourir le catalogue à chaque visite.
- Mode cache sécurisé limité aux campagnes actives (plus aux campagnes programmées).
- Prévisualisation : règle appliquée par article et comptage exact par règle sur tout le catalogue.
- Les campagnes 1.0.x sont lues comme une règle unique, sans migration.

## 1.0.4 — padding bandeau admin

- Marge interne du bandeau Verre (titre, CTA, tuiles) pour éviter le collage aux bords.

## 1.0.3 — version de déploiement

- ZIP de référence pour les boutiques : notifications de mise à jour GitHub, admin Verre, onglets sans reload.
- Inclut le correctif Plugin Check post-1.0.2.

## 1.0.2 — admin Verre + onglets sans reload

- Shell admin aligné Facturation / Geo (bandeau navy, tuiles, tokens `--g2rd-*`).
- Polices Instrument Sans et IBM Plex Mono embarquées (OFL).
- Navigation des onglets en hash (`#campaigns`, …) sans recharger la page.

## 1.0.1 — notifications de mise à jour

- Updater intégré : WordPress interroge les GitHub Releases et propose le ZIP officiel.
- Les sites doivent d’abord installer 1.0.1 manuellement ; les versions suivantes arriveront en notification dans Extensions.

## 1.0.0 — première version de recette

- Gestion des campagnes par label, catégorie, profil et dates.
- Tarifs dynamiques WooCommerce, exclusions et règles de non-cumul.
- Configuration Wholesale, séparation des profils et compatibilité de principe HPOS.
- Prévisualisation paginée, tableau de bord, calendrier, historique, statistiques et CSV.
- Métadonnées de commande, économies informatives, hooks de facture PDF.
- Contrôles PHP et JavaScript, tests autonomes du moteur.
- Chaîne CI/CD GitHub Actions (tests, Plugin Check, sécurité, release ZIP + SBOM).
- Les tests d'intégration, le filtre de prix dynamique et l'historique légal des prix restent à réaliser.
