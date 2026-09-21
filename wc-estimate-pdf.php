<?php
/**
 * Plugin Name: Order Estimate PDF
 * Description: Generates a branded Estimate Report PDF for WooCommerce orders using Dompdf, hardened for Hostinger shared hosting (see README.txt). Attaches the PDF to WooCommerce's own order emails, offers an auto-download on the Thank You page, and a Download/Print column in wp-admin.
 * Version: 1.0.1
 * Author: World Nova Technologies
 * Text Domain: order-estimate-pdf
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/worldnovatechnologies/wc-estimate-pdf-dompdf/
 */

// Block direct file access - this file must only ever be loaded by WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_ESTIMATE_PDF_VERSION', '1.0.1' );
define( 'WC_ESTIMATE_PDF_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_ESTIMATE_PDF_FILE', __FILE__ );

/* ---------------------------------------------------------------------
 * GitHub-based automatic updates (Plugin Update Checker by YahnisElsts)
 * https://github.com/YahnisElsts/plugin-update-checker
 * ------------------------------------------------------------------ */
require_once WC_ESTIMATE_PDF_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$wcEstimatePdfUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/worldnovatechnologies/wc-estimate-pdf-dompdf/',
    __FILE__,
    'wc-estimate-pdf-dompdf'
);

// Read version info from GitHub "Releases" rather than tags/branches.
$wcEstimatePdfUpdateChecker->getVcsApi()->enableReleaseAssets();

// Optional: if your GitHub repo is private, uncomment and set a token
// with at least "Contents: Read-only" access (Settings > Developer settings
// > Personal access tokens > Fine-grained tokens on GitHub). Never commit
// the token itself into the repo - store it in wp-config.php instead, e.g.
// define( 'WC_ESTIMATE_PDF_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx' );
// if ( defined( 'WC_ESTIMATE_PDF_GITHUB_TOKEN' ) && WC_ESTIMATE_PDF_GITHUB_TOKEN ) {
// 	$wcEstimatePdfUpdateChecker->setAuthentication( WC_ESTIMATE_PDF_GITHUB_TOKEN );
// }

final class WC_Estimate_PDF_Plugin {

	const ACTION_DOWNLOAD = 'wc_estimate_pdf_download';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'check_woocommerce' ) );

		require_once WC_ESTIMATE_PDF_DIR . 'includes/class-wc-estimate-pdf-settings.php';
		require_once WC_ESTIMATE_PDF_DIR . 'includes/class-wc-estimate-pdf-order.php';
		require_once WC_ESTIMATE_PDF_DIR . 'includes/class-wc-estimate-pdf-generator.php';

		WC_Estimate_PDF_Settings::init();

		// Customer-facing button (My Account > Orders > View Order).
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_customer_button' ) );

		// Admin: Orders list column + row action, order edit screen meta box.
		add_filter( 'woocommerce_admin_order_actions', array( __CLASS__, 'add_admin_order_action' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_admin_meta_box' ) );
		self::hook_admin_order_column();

		// Step 1: attach the PDF straight onto WooCommerce's own order emails
		// (new_order to the admin, customer_processing_order / customer_on_hold_order
		// / customer_completed_order / customer_invoice to the customer) - no
		// separate email is sent by this plugin, and no manual "email" button.
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attach_pdf_to_emails' ), 10, 3 );

		// Step 2: auto-download in the customer's browser after checkout.
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_auto_download' ), 20 );

		add_action( 'init', array( __CLASS__, 'maybe_handle_download' ) );
	}

	public static function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p><strong>Order Estimate PDF</strong> requires WooCommerce to be active.</p></div>';
			} );
		}
	}

	protected static function download_url( $order_id, $order_key, $force_download = false ) {
		$args = array(
			self::ACTION_DOWNLOAD => $order_id,
			'key'                 => $order_key,
			'_wpnonce'            => wp_create_nonce( 'wc_estimate_pdf_' . $order_id ),
		);
		if ( $force_download ) {
			$args['dl'] = 1;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/* ---------------------------------------------------------------------
	 * Customer-facing button
	 * ------------------------------------------------------------------ */

	public static function render_customer_button( $order ) {
		if ( ! $order instanceof WC_Order ) return;
		$url = self::download_url( $order->get_id(), $order->get_order_key() );
		echo '<p style="margin-top:15px;"><a class="button" href="' . esc_url( $url ) . '" target="_blank">Download Estimate PDF</a></p>';
	}

	/* ---------------------------------------------------------------------
	 * Step 2: auto-download on the order-received / Thank You page
	 * ------------------------------------------------------------------ */

	public static function render_auto_download( $order_id ) {
		if ( ! $order_id ) return;
		if ( WC_Estimate_PDF_Settings::get( 'auto_download' ) !== '1' ) return;

		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$url = self::download_url( $order->get_id(), $order->get_order_key(), true );

		// A hidden <a download> clicked via JS works reliably on both desktop
		// and mobile browsers (Chrome/Safari/Firefox, Android and iOS) - a
		// hidden <iframe> is desktop-only in practice, since most mobile
		// browsers just show a blank frame instead of downloading inside one.
		?>
		<a id="wcep-auto-dl" href="<?php echo esc_url( $url ); ?>" download="Estimate_Report_<?php echo esc_attr( $order->get_order_number() ); ?>.pdf" style="display:none;">Estimate PDF</a>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var link = document.getElementById('wcep-auto-dl');
				if (link) { link.click(); }
			});
		</script>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Step 1: attach to WooCommerce's own order emails
	 * ------------------------------------------------------------------ */

	public static function attach_pdf_to_emails( $attachments, $email_id, $order ) {
		if ( WC_Estimate_PDF_Settings::get( 'attach_to_emails' ) !== '1' ) {
			return $attachments;
		}
		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}
		if ( ! in_array( $email_id, WC_Estimate_PDF_Settings::get_email_ids(), true ) ) {
			return $attachments;
		}

		$path = self::write_temp_pdf( $order );
		if ( $path ) {
			$attachments[] = $path;
			// The file only needs to exist for the remainder of this request
			// (wp_mail reads it synchronously right after this filter runs).
			add_action( 'shutdown', function () use ( $path ) {
				@unlink( $path );
			} );
		}

		return $attachments;
	}

	protected static function write_temp_pdf( WC_Order $order ) {
		$pdf_bytes = self::build_pdf_bytes( $order );

		$upload_dir = wp_upload_dir();
		$tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'wc-estimate-pdf-cache';
		wp_mkdir_p( $tmp_dir );

		$path = trailingslashit( $tmp_dir ) . 'Estimate_Report_' . $order->get_order_number() . '-' . wp_generate_password( 6, false ) . '.pdf';
		if ( file_put_contents( $path, $pdf_bytes ) === false ) {
			return false;
		}
		return $path;
	}

	/* ---------------------------------------------------------------------
	 * Step 4: Orders list column + row action, order edit screen meta box
	 * ------------------------------------------------------------------ */

	protected static function hook_admin_order_column() {
		// Legacy (post-based) Orders screen.
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_order_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_order_column' ), 10, 2 );
		// HPOS ("High-Performance Order Storage") Orders screen.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_order_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_hpos_order_column' ), 10, 2 );
	}

	public static function add_order_column( $columns ) {
		$columns['estimate_pdf'] = 'Estimate PDF';
		return $columns;
	}

	public static function render_order_column( $column, $post_id ) {
		if ( $column !== 'estimate_pdf' ) return;
		$order = wc_get_order( $post_id );
		self::print_column_link( $order );
	}

	public static function render_hpos_order_column( $column, $order ) {
		if ( $column !== 'estimate_pdf' ) return;
		self::print_column_link( $order );
	}

	protected static function print_column_link( $order ) {
		if ( ! $order instanceof WC_Order ) return;
		$url = self::download_url( $order->get_id(), $order->get_order_key() );
		echo '<a href="' . esc_url( $url ) . '" target="_blank" title="View / Print Estimate PDF" style="text-decoration:none;">'
			. '<span class="dashicons dashicons-media-document" style="font-size:20px;width:20px;height:20px;"></span>'
			. '</a>';
	}

	public static function add_admin_order_action( $actions, $order ) {
		$order_id = is_a( $order, 'WC_Order' ) ? $order->get_id() : $order;
		$wc_order = wc_get_order( $order_id );
		if ( ! $wc_order ) return $actions;

		$actions['estimate_pdf'] = array(
			'url'    => self::download_url( $order_id, $wc_order->get_order_key() ),
			'name'   => 'Estimate PDF',
			'action' => 'estimate_pdf preview',
		);
		return $actions;
	}

	public static function add_admin_meta_box() {
		$screen = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box( 'wc_estimate_pdf_box', 'Estimate PDF', array( __CLASS__, 'render_admin_meta_box' ), $screen, 'side', 'default' );
	}

	public static function render_admin_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) return;

		$view = self::download_url( $order->get_id(), $order->get_order_key() );
		echo '<p><a class="button button-primary" style="width:100%;text-align:center;" href="' . esc_url( $view ) . '" target="_blank">View / Print PDF</a></p>';
		echo '<p class="description">Opens the PDF in a new tab - use your browser\'s own Print button there.</p>';
	}

	/* ---------------------------------------------------------------------
	 * Streaming endpoint
	 * ------------------------------------------------------------------ */

	public static function maybe_handle_download() {
		if ( isset( $_GET[ self::ACTION_DOWNLOAD ] ) ) {
			self::stream_pdf(
				absint( $_GET[ self::ACTION_DOWNLOAD ] ),
				isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '',
				! empty( $_GET['dl'] )
			);
		}
	}

	protected static function get_authorized_order( $order_id, $order_key ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'Order not found.', 'Not found', array( 'response' => 404 ) );
		}

		$is_admin    = current_user_can( 'manage_woocommerce' );
		$is_owner    = is_user_logged_in() && $order->get_customer_id() === get_current_user_id();
		$key_matches = $order_key && hash_equals( $order->get_order_key(), $order_key );

		if ( ! $is_admin && ! $is_owner && ! $key_matches ) {
			wp_die( 'You are not allowed to view this estimate.', 'Forbidden', array( 'response' => 403 ) );
		}

		// The order key is a permanent, unguessable bearer secret - the same
		// mechanism WooCommerce's own guest order-tracking/pay-for-order URLs
		// rely on - so a matching key is sufficient authorization on its own.
		// A WordPress nonce, by contrast, expires after ~24h; requiring it
		// unconditionally (as before) meant a customer's own "Download
		// Estimate PDF" link would start 403'ing if bookmarked or reopened
		// from an old tab a day later, even though their key never expired.
		// Only fall back to the nonce when there's no key match, i.e. when
		// authorization rests solely on the current session (admin/owner).
		if ( ! $key_matches ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wc_estimate_pdf_' . $order_id ) ) {
				wp_die( 'Security check failed - please refresh the page and try again.', 'Forbidden', array( 'response' => 403 ) );
			}
		}

		return $order;
	}

	protected static function build_pdf_bytes( WC_Order $order ) {
		$data = WC_Estimate_PDF_Order::get_data( $order );

		$shop = array(
			'name'    => WC_Estimate_PDF_Settings::get( 'shop_name', get_bloginfo( 'name' ) ),
			'address' => WC_Estimate_PDF_Settings::get( 'shop_address' ),
			'phone'   => WC_Estimate_PDF_Settings::get( 'shop_phone' ),
			'email'   => WC_Estimate_PDF_Settings::get( 'shop_email' ),
			'website' => WC_Estimate_PDF_Settings::get( 'shop_website', home_url( '/' ) ),
		);

		$generator = new WC_Estimate_PDF_Generator( $shop );
		return $generator->render( $data );
	}

	/**
	 * Streams the PDF. Bytes are fully generated in memory first
	 * (see WC_Estimate_PDF_Generator::render), then written out in one go -
	 * nothing else runs after headers are sent.
	 */
	protected static function stream_pdf( $order_id, $order_key, $force_download = false ) {
		$order     = self::get_authorized_order( $order_id, $order_key );
		$pdf_bytes = self::build_pdf_bytes( $order );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . ( $force_download ? 'attachment' : 'inline' ) . '; filename="Estimate_Report_' . $order->get_order_number() . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_bytes ) );
		echo $pdf_bytes;
		exit;
	}
}

WC_Estimate_PDF_Plugin::init();
