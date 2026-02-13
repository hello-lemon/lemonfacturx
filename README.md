# LemonFacturX

Module Dolibarr pour la génération automatique de factures **Factur-X EN16931** (PDF/A-3 avec XML CrossIndustryInvoice embarqué).

Chaque facture client générée dans Dolibarr est automatiquement convertie au format Factur-X, conforme aux règles **BR-FR** (norme XP Z12-012 V1.2.0) pour la facturation électronique française.

## Prérequis

- **Dolibarr** 22.0.x
- **PHP** 8.2+
- **Constante Dolibarr** `MAIN_PDF_FORCE_FONT` = `pdfahelvetica` (pour embarquer les polices, requis PDF/A-3)

## Installation

1. Copier le dossier `lemonfacturx/` dans le répertoire custom de Dolibarr :

```bash
cp -r lemonfacturx/ /var/www/html/custom/
chown -R www-data:www-data /var/www/html/custom/lemonfacturx
```

2. Activer le module dans Dolibarr : **Accueil > Configuration > Modules**
3. Configurer le module dans **Accueil > Configuration > Modules > LemonFacturX** :
   - Sélectionner le compte bancaire (IBAN/BIC)
   - Choisir le moyen de paiement par défaut (virement, SEPA, prélèvement)
4. Vérifier le diagnostic en bas de la page de configuration (toutes les coches vertes = OK)
5. Ajouter dans "Divers" la constante `MAIN_PDF_FORCE_FONT` avec `pdfahelvetica` comme valeur
**

## Architecture

```
lemonfacturx/
├── core/modules/modLemonFacturX.class.php   # Descripteur module (n° 500200)
├── class/actions_lemonfacturx.class.php     # Hook afterPDFCreation
├── lib/
│   ├── xml_builder.php                      # Générateur XML EN16931
│   └── inject_facturx.php                   # Injection PDF (subprocess)
├── admin/setup.php                          # Page de configuration
├── langs/fr_FR/lemonfacturx.lang            # Traductions
└── vendor/                                  # Lib atgp/factur-x v3.0.0
```

## Fonctionnement

Le module se branche sur le hook `afterPDFCreation` (contexte `pdfgeneration`). À chaque génération de PDF facture client :

1. **Vérification** des infos obligatoires (vendeur, acheteur, IBAN) — affiche des warnings si incomplet
2. **Génération du XML** CrossIndustryInvoice EN16931 avec les données de la facture Dolibarr
3. **Injection** du XML dans le PDF via la lib `atgp/factur-x` (subprocess séparé pour éviter le conflit FPDF/TCPDF)

L'injection se fait dans un **subprocess PHP séparé** (`inject_facturx.php`) pour éviter le conflit de classes entre FPDF (utilisé par atgp/factur-x) et TCPDF (utilisé par Dolibarr).

## Données mappées (Dolibarr → Factur-X)

| Champ Factur-X | Source Dolibarr |
|---|---|
| BT-1 Invoice ID | `$invoice->ref` |
| BT-2 Issue date | `$invoice->date` |
| BT-3 Type code | 380 (facture) / 381 (avoir) |
| BT-9 Due date | `$invoice->date_lim_reglement` |
| Seller | `$mysoc` (config société) |
| Buyer | `$invoice->thirdparty` |
| Seller SIREN (BT-30) | 9 premiers chiffres de `$mysoc->idprof2` |
| Seller email (BT-34) | `$mysoc->email` |
| Buyer email (BT-49) | `$thirdparty->email` ou 1er contact |
| Lines | `$invoice->lines[]` |
| IBAN / BIC | Compte bancaire Dolibarr sélectionné |
| Payment means | Configurable (30=virement, 58=SEPA) |

### Mentions légales FR (BR-FR-05)

Le XML inclut automatiquement les notes obligatoires :
- **PMD** : pénalités de retard (3x taux d'intérêt légal, art. L.441-10)
- **PMT** : indemnité forfaitaire de recouvrement (40 €)
- **AAB** : escompte pour paiement anticipé

## Constantes du module

| Constante | Type | Défaut | Description |
|---|---|---|---|
| `LEMONFACTURX_ENABLED` | int | 1 | Activer/désactiver la conversion |
| `LEMONFACTURX_BANK_ACCOUNT` | int | 0 | ID du compte bancaire Dolibarr |
| `LEMONFACTURX_PAYMENT_MEANS` | string | 30 | Code moyen de paiement |

## Dépendances embarquées

Le dossier `vendor/` contient les libs nécessaires (pas de Composer requis sur le serveur) :

- `atgp/factur-x` v3.0.0 — génération PDF Factur-X
- `setasign/fpdi` — lecture/écriture PDF
- `setasign/fpdf` — moteur PDF (utilisé par atgp, **pas** par Dolibarr)
- `smalot/pdfparser` — parsing PDF
- `symfony/polyfill-mbstring` — compatibilité mbstring

## Conformité PDF/A-3

La conformité PDF/A-3 est assurée par :
- **Polices embarquées** : constante Dolibarr `MAIN_PDF_FORCE_FONT=pdfahelvetica` (à configurer via `/admin/const.php`)
- **Annotations /F flag** : patch appliqué dans `vendor/setasign/fpdf/fpdf.php` (ajout `/F 4` aux liens)
- **Profil ICC sRGB** + **métadonnées XMP** : gérés par la lib `atgp/factur-x`

> **Note** : si un module tiers (ex: milestone/jalons) hardcode la police `'Helvetica'`, il faudra le patcher pour utiliser `pdf_getPDFFont($outputlangs)`.

## Validation

Tester avec [B2Brouter Factur-X Validator](https://www.b2brouter.net/fr/factur-x-validator/) :
- Valid XMP, Valid XSD, Valid Schematron, Valid PDF/A-3
- Profile EN 16931 (Comfort)

## Licence

Ce module est distribué sous licence [GPLv3](https://www.gnu.org/licenses/gpl-3.0.html).

Copyright (C) 2026 SASU LEMON — https://hellolemon.fr
