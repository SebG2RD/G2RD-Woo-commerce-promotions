# Audit sécurité — G2RD WooCommerce Promotions 1.0.0

**Référentiels :** WordPress hardening, OWASP Top 10, OWASP API Security Top 10, bonnes pratiques WooCommerce  
**Score sécurité (voir AUDIT-GLOBAL) :** 74 / 100  
**Scan dynamique ZAP :** non exécuté (pas d’environnement de recette)  
**Secrets / Gitleaks :** non exécuté (pas de `.git`)

---

## 1. Synthèse

Aucune vulnérabilité **critique confirmée** de type injection SQL, XSS stockée admin évidente, élévation de privilèges ou endpoint REST ouvert n’a été trouvée dans le code source.

Les contrôles d’accès admin sont globalement corrects (`manage_woocommerce` **et** nonce). Le moteur tarifaire est serveur ; le navigateur ne décide pas du prix final.

Les risques restants portent surtout sur :

- la **fuite de tarifs Wholesale via cache page/CDN** si la config n’est pas stricte ;
- la **charge CPU/RAM** (déni de service involontaire) via `on_sale_ids` ;
- l’absence de tests d’intrusion dynamiques.

---

## 2. Matrice des surfaces

| Surface | Auth | Nonce / CSRF | Validation | Échappement | Statut |
|---|---|---|---|---|---|
| `admin_post_g2rd_promo_save` | `manage_woocommerce` | `check_admin_referer` | `input()` strict | redirects + notices `esc_html` | OK code |
| `admin_post_g2rd_promo_action` | idem | idem | `sanitize_key` + get campagne | OK | OK code |
| `admin_post_g2rd_promo_settings` | idem | idem | taxonomies / rôles whitelist | OK | OK code |
| `admin_post_g2rd_promo_export` | idem | idem | dates bornées | CSV + anti-formule | OK code |
| `wp_ajax_g2rd_promo_preview` | `guard()` | `check_ajax_referer` | même `input()` | JSON contrôlé | OK code |
| Front prix | session WP/WC | n/a | contexte rôle serveur | n/a | OK code |
| Store API | WooCommerce | Woo | recalcul panier serveur | n/a | **non testé runtime** |
| REST custom | — | — | — | — | Aucun endpoint |

**Rappel appliqué dans le code :** un nonce ne remplace pas `current_user_can` — les deux sont présents sur les actions mutantes.

---

## 3. Anomalies sécurité

### SEC-001 — Risque de fuite Wholesale via cache partagé
- **Sévérité :** P1
- **Fichiers :** `includes/Pricing.php` (`cache_control`, `variation_hash`), `includes/Repository.php` (`invalidate`)
- **Description :** le mode `cache_safe` (défaut `true`) pose `DONOTCACHEPAGE` et `nocache_headers()` tant qu’une campagne est active/programmée. Si un commerçant désactive ce mode, ou si un CDN/proxy ignore ces en-têtes, une page HTML contenant un prix Wholesale peut être servie à un visiteur.
- **Reproduction (recette) :** activer campagne B2B, connecter un compte Wholesale, charger une fiche, purger partiellement, ouvrir en navigation privée.
- **Impact financier :** exposition de marges B2B.
- **Impact sécurité :** violation de confidentialité commerciale (OWASP A01 Broken Access Control côté présentation).
- **Correction :** conserver `cache_safe` par défaut ; documenter CDN ; cookies de cache séparés par rôle ; alerte admin si `cache_safe` off + wholesale on.
- **Tests :** 2 navigateurs / 2 rôles + WP Rocket + CDN staging.

### SEC-002 — Parcours « On sale » = charge abusive (DoS applicatif)
- **Sévérité :** P0 (disponibilité)
- **Fichier :** `includes/Pricing.php` ~284-296 (`on_sale_ids`)
- **Description :** pour enrichir le transient `wc_products_onsale`, le plugin pagine **tous** les candidats et appelle `quote()` sur chaque variation visible.
- **Impact :** timeout PHP, saturation MySQL, boutique indisponible pendant une campagne large.
- **Correction :** plafond dur, calcul différé, indexation async, ou désactivation du filtre sur gros catalogues.
- **Tests :** 1k / 10k / 50k produits avec Query Monitor.

### SEC-003 — Export CSV / stats : coût élevé pour un compte shop_manager
- **Sévérité :** P2
- **Fichier :** `includes/Admin.php` `stats_data` / `export`
- **Description :** jusqu’à 5 000 commandes scannées par requête authentifiée légitime. Pas une faille d’auth, mais abus possible par compte compromis `shop_manager`.
- **Correction :** file d’attente Action Scheduler, pagination serveur, rate-limit.
- **Tests :** période large sur boutique de recette riche en commandes.

### SEC-004 — Injection CSV partiellement traitée
- **Sévérité :** P3
- **Fichier :** `Admin.php` `export` (préfixe `'` si nom commence par `=+-@`)
- **Description :** mitigation présente sur le nom de campagne. Autres colonnes numériques/contrôlées. Risque résiduel faible.
- **Correction :** normaliser toutes les cellules texte ; documenter ouverture Excel.

### SEC-005 — Paramètre `$_GET['page']` non sanitizé à l’enqueue
- **Sévérité :** P3
- **Fichier :** `Admin.php` `assets()`
- **Description :** comparaison stricte à la constante `g2rd-promotions` sans `sanitize_key`. Pas d’injection directe observée.
- **Correction :** `sanitize_key( wp_unslash( $_GET['page'] ?? '' ) )`.

### SEC-006 — Messages d’erreur admin non i18n / echo direct (bootstrap)
- **Sévérité :** P4
- **Fichier :** `g2rd-woocommerce-promotions.php`
- **Description :** notices hardcodées. Contenu fixe, pas de donnée utilisateur → pas de XSS. Amélioration qualité.

---

## 4. Contrôles OWASP — résultats d’analyse statique

| OWASP | Résultat |
|---|---|
| A01 Broken Access Control | Capacités correctes sur admin. Risque cache Wholesale (SEC-001). Pas d’IDOR évident sur campagnes (capability globale boutique). |
| A02 Cryptographic Failures | Pas de secrets dans le code. Pas de crypto custom. |
| A03 Injection | Requêtes table via `$wpdb->insert/update/delete` ; `SHOW TABLES` préparé. Pas de SQL concaténé avec entrée utilisateur dans les chemins mutateurs. XSS admin globalement échappée (`esc_html`, `esc_attr`, `esc_url`). |
| A04 Insecure Design | Prix recalculés serveur ; fail-closed Wholesale. Conception globalement défensive. |
| A05 Security Misconfiguration | Désinstall conserve données (bon). Pas de debug forcé. |
| A06 Vulnerable Components | Aucune dépendance Composer/npm runtime. |
| A07 Auth Failures | Délègue à WordPress. |
| A08 Software/Data Integrity | Pas de CI / provenance de build à auditer (ZIP manuel). |
| A09 Logging Failures | Journal d’admin limité (200). Pas de log de prix sensibles en clair côté front. |
| A10 SSRF | Aucun appel HTTP sortant dans le plugin. |

### API Security

Pas d’API custom. Les interactions Store API passent par les hooks WooCommerce. **Permission_callback N/A.** Risque principal : manipulation panier côté client → **recalcul serveur** dans `apply_cart` (conception OK, runtime non prouvé ici).

---

## 5. Sécurité financière — analyse code

| Attaque | Protection observée dans le code | Preuve runtime |
|---|---|---|
| Modifier % depuis le navigateur | % issu de la campagne SQL, pas du client | Non testé HTTP |
| Modifier prix unitaire panier | `reset_cart` + `quote()` + `set_price` serveur | Mock OK |
| Usurper rôle Wholesale | `context()` lit rôles WP serveur | Mock OK |
| Campagne expirée | `Rules::active` sur `time()` à chaque quote | Mock OK |
| Double application | reset avant apply + tests | Mock OK |
| Meta panier forgée | réécrit à chaque calcul ; snapshot commande vérifie cohérence prix | Mock partiel |
| Accès settings anonymes | `guard()` 403 | Non testé HTTP |

---

## 6. Protection des données

- Pas de transmission externe.
- Snapshots commande : données commerciales (campagne, taux, économies) — légitimes pour facture.
- Export CSV protégé par capability.
- Rétention : désinstall ne purge pas par défaut (choix documenté).
- **Recommandation :** politique de conservation des logs admin (durée) à expliciter pour le client.

---

## 7. Vulnérabilités confirmées vs risques

| Type | Items |
|---|---|
| Vulnérabilités confirmées (code) | Aucune RCE/SQLi/XSS/CSRF admin critique confirmée |
| Risques potentiels élevés | SEC-001, SEC-002 |
| Non testés | ZAP, fuzzing Store API, comptes Wholesale réels, multisite |

---

## 8. Corrections recommandées (ordre)

1. Plafonner / réécrire `on_sale_ids` (SEC-002).
2. Durcir la politique cache Wholesale + alerte admin (SEC-001).
3. Déplacer stats/export en tâche asynchrone (SEC-003).
4. Sanitiser `$_GET['page']` (SEC-005).
5. Ajouter tests de sécurité automatisés (capability refusée, nonce absent).
