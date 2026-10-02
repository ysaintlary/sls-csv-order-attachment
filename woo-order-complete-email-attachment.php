<?php
/**
 * Plugin Name:       YS | Woo Order Email Attachment
 * Plugin URI:        https://github.com/ysaintlary/woo-order-complete-email-attachment
 * Description:       Attache un bon de commande XLSX à l'e-mail « Commande terminée » de WooCommerce.
 * Version: 1.5.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Yves Saint-Lary
 * Author URI:        https://ysaintlary.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woo-order-complete-email-attachment
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.1
 * WC tested up to:   9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lib/wp-plugin-base/wp-plugin-base-runtime-updater.php';
require_once __DIR__ . '/lib/xlsxwriter/xlsxwriter.class.php';

define( 'WOCEA_VERSION', '1.5.0' );
define( 'WOCEA_EAN_META_KEY', '_alg_ean' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * @param WC_Product|null $product Product object.
 * @return string EAN code or empty string.
 */
function WOCEA_get_ean( $product ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	if ( ! $product ) {
		return '';
	}

	$ean = $product->get_meta( WOCEA_EAN_META_KEY );
	if ( '' !== $ean && false !== $ean ) {
		return (string) $ean;
	}

	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$ean = $parent->get_meta( WOCEA_EAN_META_KEY );
			if ( '' !== $ean && false !== $ean ) {
				return (string) $ean;
			}
		}
	}

	if ( method_exists( $product, 'get_global_unique_id' ) ) {
		$gtin = $product->get_global_unique_id();
		if ( '' !== $gtin ) {
			return (string) $gtin;
		}
	}

	return '';
}

/**
 * @return string|false Directory path or false on failure.
 */
function WOCEA_get_upload_dir() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . 'woo-order-complete-email-attachment';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $htaccess, "Require all denied\n" );
	}

	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	return $dir;
}

/**
 * @param array    $attachments Existing attachments.
 * @param string   $email_id    Email identifier.
 * @param WC_Order $order       Order object.
 * @param WC_Email $email       Email object.
 * @return array Modified attachments.
 */
function WOCEA_attach_xlsx( $attachments, $email_id, $order, $email ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( 'customer_completed_order' !== $email_id ) {
		return $attachments;
	}

	if ( ! $order instanceof \WC_Order ) {
		return $attachments;
	}

	$dir = WOCEA_get_upload_dir();
	if ( ! $dir ) {
		return $attachments;
	}

	$order_number = $order->get_order_number();
	$file_path    = $dir . '/SLS-toblerone-BL-' . $order_number . '.xlsx';

	$writer = new \XLSXWriter(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	$header = array(
		'Commande'         => 'string',
		'UGS'              => 'string',
		'Gencod'           => 'string',
		"Libellé article"  => 'string',
		'Quantité'         => 'integer',
		"Prix d'achat HT"  => '#,##0.00',
	);

	$writer->writeSheetHeader( 'Bon de commande', $header, array( 'suppress_row' => false ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			continue;
		}

		$product = $item->get_product();
		$sku     = $product ? (string) $product->get_sku() : '';
		$ean     = $product ? WOCEA_get_ean( $product ) : '';
		$name    = $item->get_name();
		$qty     = $item->get_quantity();
		$price   = (float) $order->get_item_total( $item, false, false );

		$writer->writeSheetRow( 'Bon de commande', array( (string) $order_number, $sku, $ean, $name, $qty, $price ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	}

	$writer->writeToFile( $file_path ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

	$attachments[] = $file_path;

	add_action(
		'shutdown',
		function () use ( $file_path ) {
			if ( file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file_path );
			}
		}
	);

	return $attachments;
}
add_filter( 'woocommerce_email_attachments', 'WOCEA_attach_xlsx', 10, 4 );
