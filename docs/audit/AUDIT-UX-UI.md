# Audit UX / UI back-office — G2RD WooCommerce Promotions 1.0.0

**Score UX/UI :** 73 / 100  
**Score accessibilité :** 58 / 100 (partiellement non évalué sans axe-core)  
**Référence design :** [g2rd-connector `assets/admin/src/style.css`](https://github.com/SebG2RD/g2rd-connector/blob/main/assets/admin/src/style.css) (mode autonome « Verre »)  
**Référence Facturation :** dépôt GitHub **inaccessible (404)** — comparaison impossible

---

## 1. Design system G2RD — checklist

| Critère | Attendu Connector / Verre | Plugin Promotions | Écart |
|---|---|---|---|
| Cartes blanches | oui | `.g2rd-promo-section` fond blanc | OK |
| Radius 16 px | oui | `border-radius:16px` | OK |
| Ombres légères | oui | même ombre double soft | OK |
| Accent WP dynamique | `--wp-admin-theme-color` | `--gp-accent:var(--wp-admin-theme-color,#3858e9)` | OK |
| Icônes SVG linéaires | stroke ~1.75 | `stroke-width="1.75"` | OK |
| Interrupteurs | FormToggle 44×26 Connector | toggle custom 38×22 | **Écart taille / composant** |
| Champs « en creux » | inset shadow, min-height 44 | fond `#f6f7f7`, min-height 38 | **Proche mais plus petit** |
| Pastilles statut | chips | `.g2rd-promo-chip` | OK |
| Scope CSS | préfixe dédié | `.g2rd-promo` uniquement | OK (ne pollue pas le reste de l’admin) |
| Mode console thème FSE | Connector a un mode console | **Absent** (readme l’avoue) | Manquant |
| Responsive | breakpoints 782/600 | 1150/600 | OK correct, breakpoints différents |

**Verdict UI :** bonne imitation autonome du Verre, sans dépendance build. Pas un clone pixel-perfect du Connector (tailles de contrôles, pas de `components-form-toggle`).

---

## 2. Parcours commerçant (simplicité)

Points positifs :

- Navigation par onglets claire (Vue d’ensemble → Campagnes → … → Aide).
- Éditeur en deux colonnes : paramètres à gauche, prévisualisation / publication à droite.
- Textes explicites (« fin exclusive », Wholesale fail-closed, promotions natives verrouillées).
- Confirmation JS sur suppression.
- Diagnostic d’intégrations honnête (« détection technique, pas un test de bout en bout »).

Points faibles :

- Sélection d’exclusions produits **par IDs numériques** uniquement → difficile pour un non-technique.
- Listes `<select multiple>` de toutes les termes : peut devenir illisible avec des centaines de labels (même avec recherche JS).
- Pas d’avertissement « modifications non enregistrées » si on quitte l’éditeur.
- Calendrier = liste, pas de vision mensuelle.
- Stats : jargon « CA HT net », « rapport partiel » — acceptable mais dense.
- Tout l’UI est en français ; pas de chaîne EN complète (i18n partiel via `esc_html__` / `__` seulement sur certains messages).

---

## 3. Accessibilité (WCAG 2.2 AA — revue code)

| Point | Observation |
|---|---|
| Labels de champs | Présents (`<label for=…>`) |
| `aria-label` recherche | Présent |
| `aria-live` prévisualisation | Présent |
| `aria-busy` pendant fetch | Présent |
| Focus visible | `focus-visible` outline accent | 
| Icônes décoratives | `aria-hidden="true"` |
| Contraste | Couleurs WP admin classiques — **non mesuré** (outil contraste non lancé) |
| Navigation clavier | Formulaires natifs OK en principe ; toggles custom **non testés** au clavier |
| Cibles 44×44 | Plusieurs boutons / toggles **sous** 44 px (écart Connector) |
| `prefers-reduced-motion` | Présent |
| Lecteur d’écran | **Non testé** (NVDA/VoiceOver) |
| axe-core | **Non exécuté** |

**Anomalies UX/A11y :**

- **P2-UX-001** — toggles / boutons sous la taille cible 44 px.
- **P3-UX-002** — pas de piège focus / skip link spécifique (page admin WP standard).
- **P3-UX-003** — messages de confirmation destructifs = `window.confirm` seulement (pas de modale accessible G2RD).
- **P3-I18N-001** — dossier `languages/` absent ; textdomain chargé quand même.

---

## 4. Front boutique

Le plugin **n’ajoute pas** de CSS/JS front : stratégie correcte pour réutiliser badges/prix du thème Drakkar (Elessi child présent localement, non audité en rendu).

Risque UX front : si le thème suppose des prix lookup ou cache HTML, incohérences possibles (voir compatibilité).

---

## 5. Recommandations

1. Remplacer les IDs produits exclus par un sélecteur AJAX (recherche nom/SKU).
2. Aligner toggles/champs sur les dimensions Connector (44 px).
3. Ajouter warning dirty-form avant navigation.
4. Prévoir fichiers `.pot` / FR-EN.
5. Lancer axe + parcours clavier sur une install WP avant validation UX finale.
