<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Estimate_PDF_Settings {

	const OPTION_KEY = 'wc_estimate_pdf_settings';

	/** WooCommerce email IDs we can attach the Estimate PDF to. */
	public static function attachable_emails() {
		return array(
			'new_order'                  => 'New order (sent to the shop admin)',
			'customer_processing_order'  => 'Processing order (sent to the customer)',
			'customer_on_hold_order'     => 'On-hold order (sent to the customer - typical for bank transfer/UPI orders)',
			'customer_completed_order'   => 'Completed order (sent to the customer)',
			'customer_invoice'           => 'Order details / invoice (sent to the customer)',
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			'Estimate PDF Settings',
			'Estimate PDF',
			'manage_woocommerce',
			'wc-estimate-pdf-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::OPTION_KEY, self::OPTION_KEY, array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$clean = array();
		$text_fields = array(
			'shop_name', 'shop_address', 'shop_phone', 'shop_email', 'shop_website',
			'bank_name', 'account_holder', 'account_number', 'ifsc', 'branch', 'gpay_number',
		);
		foreach ( $text_fields as $field ) {
			$clean[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		}
		$clean['shop_address']  = isset( $input['shop_address'] ) ? sanitize_textarea_field( $input['shop_address'] ) : '';
		$clean['terms']         = isset( $input['terms'] ) ? sanitize_textarea_field( $input['terms'] ) : '';
		$clean['auto_download'] = ! empty( $input['auto_download'] ) ? '1' : '';

		$clean['attach_to_emails'] = ! empty( $input['attach_to_emails'] ) ? '1' : '';
		$clean['email_ids']        = array();
		if ( ! empty( $input['email_ids'] ) && is_array( $input['email_ids'] ) ) {
			$known = array_keys( self::attachable_emails() );
			foreach ( $input['email_ids'] as $id ) {
				if ( in_array( $id, $known, true ) ) {
					$clean['email_ids'][] = $id;
				}
			}
		}

		return $clean;
	}

	public static function get( $key, $default = '' ) {
		$opts = get_option( self::OPTION_KEY, array() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	public static function get_email_ids() {
		$opts = get_option( self::OPTION_KEY, array() );
		if ( ! empty( $opts['email_ids'] ) && is_array( $opts['email_ids'] ) ) {
			return $opts['email_ids'];
		}
		// Sensible defaults on first activation: admin's New Order email, plus
		// whichever status email the customer actually receives.
		return array( 'new_order', 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' );
	}

	/**
	 * Version string sitting in includes/vendor/dompdf right now.
	 *
	 * Deliberately local-only, no network call: v1.2.5's admin-notice +
	 * AJAX "Check for updates" button asked GitHub's releases API for the
	 * latest dompdf/dompdf tag. That's a legitimate, minimal, cached (once
	 * a day) read-only check, and it never touched this plugin's own
	 * update path - but WordPress.org reviewers commonly flag *any*
	 * outbound version-check call as looking like a second, self-hosted
	 * update channel running alongside WP.org's own updater, even when
	 * it's only checking a bundled library rather than the plugin itself.
	 * Removed for the WordPress.org resubmission (see README) - this line
	 * still tells the shop owner what's bundled, just without phoning
	 * home to find out what's newer.
	 */
	public static function bundled_dompdf_version() {
		$file = WC_ESTIMATE_PDF_DIR . 'includes/vendor/dompdf/dompdf/VERSION';
		if ( is_readable( $file ) ) {
			$version = trim( (string) file_get_contents( $file ) );
			if ( $version !== '' ) {
				return $version;
			}
		}
		return 'unknown';
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;
		?>
		<div class="wrap">
			<h1>Estimate PDF Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_KEY ); ?>
				<table class="form-table">
					<tr><th colspan="2"><h2>Shop Details</h2></th></tr>
					<tr><th>Shop Name</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[shop_name]" value="<?php echo esc_attr( self::get( 'shop_name' ) ); ?>"></td></tr>
					<tr><th>Address</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[shop_address]"><?php echo esc_textarea( self::get( 'shop_address' ) ); ?></textarea></td></tr>
					<tr><th>Phone</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[shop_phone]" value="<?php echo esc_attr( self::get( 'shop_phone' ) ); ?>"></td></tr>
					<tr><th>Support Email (shown on the PDF itself)</th><td><input type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[shop_email]" value="<?php echo esc_attr( self::get( 'shop_email' ) ); ?>" placeholder="support@yourdomain.com"></td></tr>
					<tr><th>Website</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[shop_website]" value="<?php echo esc_attr( self::get( 'shop_website' ) ); ?>"></td></tr>

					<tr><th colspan="2"><h2>Bank Details</h2></th></tr>
					<tr><th>Bank Name</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bank_name]" value="<?php echo esc_attr( self::get( 'bank_name' ) ); ?>"></td></tr>
					<tr><th>Account Holder</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[account_holder]" value="<?php echo esc_attr( self::get( 'account_holder' ) ); ?>"></td></tr>
					<tr><th>Account Number</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[account_number]" value="<?php echo esc_attr( self::get( 'account_number' ) ); ?>"></td></tr>
					<tr><th>IFSC Code</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ifsc]" value="<?php echo esc_attr( self::get( 'ifsc' ) ); ?>"></td></tr>
					<tr><th>Branch</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[branch]" value="<?php echo esc_attr( self::get( 'branch' ) ); ?>"></td></tr>
					<tr><th>GPay / PhonePe / Paytm Number</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[gpay_number]" value="<?php echo esc_attr( self::get( 'gpay_number' ) ); ?>"></td></tr>

					<tr><th colspan="2"><h2>Terms &amp; Conditions</h2></th></tr>
					<tr>
						<th>Terms (one per line)</th>
						<td><textarea class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[terms]" placeholder="Leave blank to use the default terms. You can type the rupee sign (₹) directly - it renders correctly on the PDF."><?php echo esc_textarea( self::get( 'terms' ) ); ?></textarea></td>
					</tr>

					<tr><th colspan="2"><h2>Automatic Delivery</h2></th></tr>
					<tr>
						<th>Attach to WooCommerce order emails</th>
						<td>
							<label><input type="checkbox" id="wcep-attach" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[attach_to_emails]" value="1" <?php checked( self::get( 'attach_to_emails' ), '1' ); ?>> Automatically attach the Estimate PDF to WooCommerce's own order emails below - no separate email is sent by this plugin.</label>
							<p style="margin-top:8px;">
							<?php foreach ( self::attachable_emails() as $id => $label ) : ?>
								<label style="display:block;margin:4px 0;">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_ids][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, self::get_email_ids(), true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							</p>
							<p class="description">Recipients (e.g. support@royalcrackerstpt.com) are whatever you've already set under WooCommerce &gt; Settings &gt; Emails &gt; New order - this plugin only adds the attachment, it doesn't change who receives WooCommerce's emails.</p>
						</td>
					</tr>
					<tr>
						<th>Auto-download in browser</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_download]" value="1" <?php checked( self::get( 'auto_download' ), '1' ); ?>> Automatically start downloading the Estimate PDF (desktop and mobile browsers) when the customer lands on the order-received / Thank You page after checkout.</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<table class="form-table">
				<tr><th colspan="2"><h2>Dompdf Library</h2></th></tr>
				<tr>
					<th>Bundled version</th>
					<td>
						<code><?php echo esc_html( self::bundled_dompdf_version() ); ?></code>
						<p class="description">To update, replace <code>includes/vendor/dompdf</code> with a fresh copy (via Composer, or by downloading a packaged release from <a href="https://github.com/dompdf/dompdf/releases" target="_blank" rel="noopener">dompdf's GitHub releases</a> and copying it in).</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
