<?php
/**
 * Plugin Name:       SLS csv order attachment to completed order email notification
 * Plugin URI:        https://github.com/ysaintlary/sls-csv-order-attachment
 * Description:       Attache un bon de commande CSV à l'e-mail « Commande terminée » de WooCommerce.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Yves Saint-Lary
 * Author URI:        https://ysaintlary.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       sls-csv-order-attachment
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.1
 * WC tested up to:   9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SLS_COA_VERSION', '1.1.0' );
define( 'SLS_COA_EAN_META_KEY', '_alg_ean' );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Sanitize a text value for CSV injection prevention.
 *
 * @param string $value Cell value.
 * @return string Sanitized value.
 */
function SLS_COA_sanitize_csv_cell( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- prefixed constant-style per project convention
	$value = (string) $value;
	if ( '' === $value ) {
		return $value;
	}
	$first = $value[0];
	if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		return "'" . $value;
	}
	return $value;
}

/**
 * Build a CSV line with semicolon separator and CRLF ending.
 *
 * Values are only quoted when they contain a semicolon, double-quote or newline.
 *
 * @param array $fields List of field values.
 * @return string CSV line in Windows-1252 encoding.
 */
function SLS_COA_build_csv_line( $fields ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- prefixed constant-style per project convention
	$escaped = array();
	foreach ( $fields as $field ) {
		$field = (string) $field;
		if ( preg_match( '/[;"\r\n]/', $field ) ) {
			$field = '"' . str_replace( '"', '""', $field ) . '"';
		}
		$escaped[] = $field;
	}
	return implode( ';', $escaped ) . "\r\n";
}

/**
 * Resolve the EAN / GTIN code for a product.
 *
 * Cascade: product meta → parent meta (for variations) → WC native global_unique_id.
 *
 * @param WC_Product|null $product Product object.
 * @return string EAN code or empty string.
 */
function SLS_COA_get_ean( $product ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- prefixed constant-style per project convention
	if ( ! $product ) {
		return '';
	}

	$ean = $product->get_meta( SLS_COA_EAN_META_KEY );
	if ( '' !== $ean && false !== $ean ) {
		return (string) $ean;
	}

	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$ean = $parent->get_meta( SLS_COA_EAN_META_KEY );
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
 * Format a price for the CSV: comma decimal, 2 decimals, "0" when zero.
 *
 * @param float $price Price value.
 * @return string Formatted price.
 */
function SLS_COA_format_price( $price ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- prefixed constant-style per project convention
	$price = (float) $price;
	if ( 0.0 === $price ) {
		return '0';
	}
	return number_format( $price, 2, ',', '' );
}

/**
 * Ensure the upload directory exists with security files.
 *
 * @return string|false Directory path or false on failure.
 */
function SLS_COA_get_upload_dir() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- prefixed constant-style per project convention
	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . 'sls-csv-order-attachment';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a security file, no WP_Filesystem needed
		file_put_contents( $htaccess, "Require all denied\n" );
	}

	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a security file, no WP_Filesystem needed
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	return $dir;
}

/**
 * Attach a CSV purchase order to the completed order email.
 *
 * @param array    $attachments Existing attachments.
 * @param string   $email_id    Email identifier.
 * @param WC_Order $order       Order object.
 * @param WC_Email $email       Email object.
 * @return array Modified attachments.
 */
function SLS_COA_attach_csv( $attachments, $email_id, $order, $email ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- prefixed constant-style per project convention; $email required by filter signature
	if ( 'customer_completed_order' !== $email_id ) {
		return $attachments;
	}

	if ( ! $order instanceof \WC_Order ) {
		return $attachments;
	}

	$dir = SLS_COA_get_upload_dir();
	if ( ! $dir ) {
		return $attachments;
	}

	$order_number = $order->get_order_number();
	$file_path    = $dir . '/toblerone-slsagency-commande-' . $order_number . '.csv';

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- writing a temporary CSV file to the uploads directory
	$handle = fopen( $file_path, 'wb' );
	if ( ! $handle ) {
		return $attachments;
	}

	// UTF-8 BOM so Excel interprets the file correctly.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( $handle, "\xEF\xBB\xBF" );

	// Header line — two spaces between "Prix" and "d'achat" to match the reference file.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( $handle, SLS_COA_build_csv_line( array( 'Commande', 'UGS', 'Gencod', "Libellé article", 'Quantité', "Prix  d'achat HT" ) ) );

	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			continue;
		}

		$product = $item->get_product();
		$sku     = $product ? SLS_COA_sanitize_csv_cell( $product->get_sku() ) : '';
		$ean     = $product ? SLS_COA_get_ean( $product ) : '';
		$name    = SLS_COA_sanitize_csv_cell( $item->get_name() );
		$qty     = $item->get_quantity();
		$price   = SLS_COA_format_price( $order->get_item_total( $item, false, false ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $handle, SLS_COA_build_csv_line( array( $order_number, $sku, $ean, $name, $qty, $price ) ) );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	fclose( $handle );

	$attachments[] = $file_path;

	// Schedule cleanup at end of request.
	add_action(
		'shutdown',
		function () use ( $file_path ) {
			if ( file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cleaning up temporary file
				unlink( $file_path );
			}
		}
	);

	return $attachments;
}
add_filter( 'woocommerce_email_attachments', 'SLS_COA_attach_csv', 10, 4 );
