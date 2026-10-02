=== SLS csv order attachment to completed order email notification ===
Contributors: ysaintlary
Tags: woocommerce, csv, email, order, attachment
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Attache un bon de commande CSV à l'e-mail « Commande terminée » de WooCommerce.

== Description ==

Ce plugin génère automatiquement un fichier CSV contenant le détail des articles commandés et le joint à l'e-mail « Commande terminée » envoyé au client.

Le fichier CSV est compatible avec Excel (encodage Windows-1252, séparateur point-virgule, virgule décimale).

== Installation ==

1. Téléverser le dossier `sls-csv-order-attachment` dans `/wp-content/plugins/`
2. Activer le plugin via le menu « Extensions » de WordPress
3. Aucune configuration nécessaire : le CSV est automatiquement joint aux e-mails de commande terminée

== Changelog ==

= 1.1.0 =
* Encodage UTF-8 avec BOM (accents corrects dans Excel)
* Nom du fichier : toblerone-slsagency-commande-XXXX.csv

= 1.0.0 =
* Version initiale
