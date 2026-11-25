# Audit conformité — annonces de réduction de prix

**Score conformité :** 22 / 100  
**Périmètre :** France / UE (B2C), TVA, facturation informative  
**Sources consultées (24/11/2026) :**

- [Légifrance — Code de la consommation, art. L112-1-1](https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000006069565/LEGISCTA000032220895/)
- [Service-Public — Affichage des prix](https://www.service-public.gouv.fr/particuliers/vosdroits/F34344) (vérifié 02/07/2024)
- [DGCCRF — règles contre les faux rabais](https://www.economie.gouv.fr/dgccrf/actualites-dgccrf/des-regles-plus-claires-contre-les-faux-rabais)

---

## 1. Règle applicable (B2C)

Toute **annonce d’une réduction de prix** doit indiquer le **prix antérieur**.  
Ce prix antérieur = **le prix le plus bas pratiqué** par le professionnel à l’égard de **tous les consommateurs** au cours des **30 jours** précédant l’application de la réduction.

Exception pour réductions successives sur une période déterminée : prix avant la **première** réduction de la période.  
Exceptions : produits périssables à altération rapide ; comparaisons avec prix d’autres professionnels (encadrement distinct).

Le non-respect peut être qualifié de **pratique commerciale trompeuse** (sanctions prévues au Code de la consommation).

**B2B (Wholesale) :** le régime L112-1-1 vise la relation consommateur. Les campagnes professionnelles restent soumises à loyauté / pratiques trompeuses générales, mais l’obligation du « plus bas 30 jours » est une problématique surtout **B2C**.

---

## 2. Ce que fait le plugin aujourd’hui

| Comportement | Effet réglementaire |
|---|---|
| Affiche un prix barré via `regular_price` WooCommerce (ou base Wholesale) | Le prix barré = **prix catalogue enregistré**, pas le plus bas 30 jours |
| Affiche un % de campagne G2RD | Annonce de réduction → **entre dans le champ L112-1-1** en B2C |
| Pas d’historique de prix pratiqués | **Impossible** de calculer le prix de référence légal |
| CHANGELOG / RECETTE / readme | Documentent explicitement l’absence de cette fonctionnalité |

### Anomalie P1-CONF-001

- **Sévérité :** P1 (bloquant mise en production B2C France)
- **Fonctionnalité :** affichage promotions catalogue / fiches / badges
- **Description :** le plugin peut présenter « −30 % » avec un prix barré égal au `regular_price`, même si le produit a été vendu moins cher dans les 30 jours précédents.
- **Impact :** risque DGCCRF / litige consommateur ; pourcentages trompeurs.
- **Impact financier :** amendes potentielles + obligation de retrait de campagne.
- **Correction :** module d’historique des prix affichés/pratiqués ; calcul du min 30 jours ; option d’afficher le % par rapport à ce référentiel ou de **ne pas afficher de %** tant que non conforme ; journal de preuves.
- **Tests :** produit baissé puis re-monté puis campagne G2RD ; vérif prix barré = min 30 j.

---

## 3. Autres points conformité / fiscalité

| Sujet | Statut |
|---|---|
| Distinction B2C / B2B dans le moteur | Présente (profils) — utile |
| TVA | Délègue à WooCommerce (pas de retouche taux) — OK conception |
| Factures : info campagne / économie | Meta + totaux informatifs — ne doivent pas double-déduire (OK code) |
| Mentions légales soldes / « solde(s) » | Non gérées (hors scope) — le commerçant ne doit pas abuser du vocabulaire |
| Preuve de prix de référence | Absente |
| Promotions successives (exception période) | Absente |
| Produits périssables | N/A probable pour Drakkar (merch culturel) mais pas de flag |

---

## 4. Recommandation opérationnelle immédiate

**Avant toute campagne B2C sur Drakkar :**

1. Ne pas considérer le plugin comme conforme L112-1-1.
2. Soit mettre en place un outil d’historique de prix (plugin dédié ou développement phase 6),  
   soit valider manuellement/juridiquement chaque opération et l’affichage du thème.
3. Documenter la responsabilité commerçant dans les CGU internes / runbook.

Le score 22 reflète une **absence fonctionnelle majeure** sur une obligation légale centrale, malgré une documentation honnête des limites.
