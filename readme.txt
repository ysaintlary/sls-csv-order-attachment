=== YS | CSV Order Attachment ===
Contributors: ysaintlary
Tags: woocommerce, xlsx, email, order, attachment
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Attache un bon de commande XLSX à l'e-mail « Commande terminée » de WooCommerce.

== Description ==

Ce plugin génère automatiquement un fichier Excel (.xlsx) contenant le détail des articles commandés et le joint à l'e-mail « Commande terminée » envoyé au client.

== Installation ==

1. Téléverser le dossier `sls-csv-order-attachment` dans `/wp-content/plugins/`
2. Activer le plugin via le menu « Extensions » de WordPress
3. Aucune configuration nécessaire : le fichier Excel est automatiquement joint aux e-mails de commande terminée

== Changelog ==

= 1.4.0 =
* Update - Renommage du repo GitHub (sls-csv-order-attachment → csv-order-attachment).
* Update - Préfixe « YS | » dans le nom du plugin.
* Update - URL du Runtime Updater mise à jour.


= 1.3.1 =
* Nom du fichier : SLS-toblerone-BL-XXXX.xlsx

= 1.3.0 =
* Fichier joint au format Excel (.xlsx) au lieu de CSV
* Bibliothèque PHP_XLSXWriter intégrée

= 1.2.0 =
* Mises à jour automatiques depuis WordPress (Runtime Updater Pack)

= 1.1.0 =
* Encodage UTF-8 avec BOM (accents corrects dans Excel)
* Nom du fichier : toblerone-slsagency-commande-XXXX.csv

= 1.0.0 =
* Version initiale
