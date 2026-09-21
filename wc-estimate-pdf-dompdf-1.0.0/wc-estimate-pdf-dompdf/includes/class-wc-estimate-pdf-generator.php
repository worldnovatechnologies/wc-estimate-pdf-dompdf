<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Builds the Estimate Report PDF for a WooCommerce order using Dompdf.
 *
 * Hostinger-shared-hosting hardening applied here (see README.txt for the
 * full write-up of what was wrong in the original generate_pdf.php):
 *  - isRemoteEnabled(false): the reference script <link>'d a CDN stylesheet
 *    that has nothing to do with the PDF (it was for an on-screen SweetAlert
 *    popup) — leaving remote fetching on just adds latency/timeout risk for
 *    zero benefit here.
 *  - Font/CSS/HTML5 caches are pointed at a wp-content/uploads folder that
 *    Dompdf can always write to, and that survives plugin updates.
 *  - Output buffers are fully flushed and error display is suppressed right
 *    before rendering, so a stray PHP notice can never get prepended to the
 *    PDF bytes and corrupt the download (the #1 cause of "PDF won't open"
 *    on shared hosting).
 *  - The PDF is always built to a string first (Dompdf::output()), then
 *    that single string is either streamed or emailed or both — unlike the
 *    reference script, which called ->stream() (which sends headers and
 *    exits the HTTP response) and then kept running PHP afterwards to save
 *    the file and send mail, silently corrupting the streamed download.
 */
class WC_Estimate_PDF_Generator {

	protected $shop;

	public function __construct( array $shop ) {
		$this->shop = $shop;
	}

	protected function money( $amount ) {
		// DejaVu Sans (bundled with Dompdf) is used only for amounts so the
		// rupee sign renders; the rest of the document stays on Arial/Helvetica.
		return '<span style="font-family: DejaVu Sans, sans-serif;">&#8377;' . number_format( (float) $amount, 2 ) . '</span>';
	}

	/**
	 * @param array $order_data See WC_Estimate_PDF_Order::get_data().
	 * @return string Raw PDF bytes.
	 */
	public function render( array $order_data ) {
		$html = $this->build_html( $order_data );

		$options = new Options();
		$options->set( 'defaultFont', 'Arial' );
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'isFontSubsettingEnabled', true );

		$cache_dir = $this->get_cache_dir();
		$options->set( 'fontDir', $cache_dir );
		$options->set( 'fontCache', $cache_dir );
		$options->set( 'tempDir', $cache_dir );
		$options->set( 'chroot', $cache_dir );

		// Flush any buffered output and silence notices so nothing can get
		// mixed into the PDF bytes we are about to generate.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		$prev_reporting = error_reporting( 0 );
		@ini_set( 'display_errors', '0' );
		@ini_set( 'memory_limit', '256M' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 );
		}

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		$pdf_bytes = $dompdf->output();

		error_reporting( $prev_reporting );

		return $pdf_bytes;
	}

	/**
	 * A writable, persistent folder for Dompdf's font/CSS cache. Using
	 * wp-content/uploads instead of the plugin's own folder means the cache
	 * survives plugin updates and doesn't depend on the plugin directory
	 * being writable by PHP (Hostinger sometimes locks that down after
	 * install, but wp-content/uploads is always writable by WordPress).
	 */
	protected function get_cache_dir() {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'wc-estimate-pdf-cache';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	protected function build_html( array $d ) {
		$shop = $this->shop;

		$rows = '';
		$sno  = 1;
		foreach ( $d['items'] as $item ) {
			$rows .= '<tr>
				<td style="width:30px;">' . $sno . '</td>
				<td>' . esc_html( $item['name'] ) . '</td>
				<td>' . $this->money( $item['price'] ) . '</td>
				<td style="text-align:center;">' . intval( $item['qty'] ) . '</td>
				<td>' . $this->money( $item['line_total'] ) . '</td>
			</tr>';
			$sno++;
		}

		$terms_html = '';
		foreach ( $d['terms'] as $term ) {
			$terms_html .= '<li>' . esc_html( $term ) . '</li>';
		}

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
<style>
	@page { margin: 20px; }
	body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; }
	th, td { border: 1px solid black; padding: 8px; text-align: left; vertical-align: top; }
	.header { text-align: center; font-size: 20px; font-weight: bold; border: none; padding-bottom: 10px; }
	.sub-header td { border: none; padding: 5px 10px; font-size: 14px; }
	.no-border { border: none !important; }
	.bold { font-weight: bold; }
	.center { text-align: center; }
	.right { text-align: right; }
</style>
</head>
<body>
<table>
	<tr>
		<td colspan="5" style="border:1px solid black; padding:0;">
			<table style="width:100%; border-collapse:collapse; font-size:14px;">
				<tr><td colspan="5" class="header" style="border-bottom:1px solid black;">Estimate Report</td></tr>
				<tr class="sub-header">
					<td colspan="2" style="border-bottom:1px solid black;"><strong>Estimate No:</strong> <?php echo esc_html( $d['estimate_no'] ); ?></td>
					<td colspan="3" class="right" style="border-bottom:1px solid black;"><strong>Date:</strong> <?php echo esc_html( $d['date'] ); ?></td>
				</tr>
				<tr class="sub-header">
					<td colspan="2" style="border-bottom:1px solid black;"><strong>Email:</strong> <?php echo esc_html( $shop['email'] ); ?></td>
					<td colspan="3" class="right" style="border-bottom:1px solid black;"><strong>Phone:</strong> <?php echo esc_html( $shop['phone'] ); ?></td>
				</tr>
				<tr>
					<td colspan="5" class="center" style="border:none;">
						<strong style="font-size:18px;"><?php echo esc_html( $shop['name'] ); ?></strong><br><br>
						<span style="font-size:13px;"><?php echo esc_html( $shop['address'] ); ?></span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<tr>
		<td colspan="2" style="border:1px solid #000; vertical-align:top; padding:10px;">
			<strong><u>Customer Details:</u></strong><br><br>
			Name: <?php echo esc_html( $d['customer_name'] ); ?><br><br>
			Mobile: <?php echo esc_html( $d['customer_phone'] ); ?><br><br>
			Email: <?php echo esc_html( $d['customer_email'] ); ?><br><br>
			Address: <?php echo esc_html( $d['customer_address'] ); ?><br><br>
			State: <?php echo esc_html( $d['customer_state'] ); ?><br><br>
			District: <?php echo esc_html( $d['customer_district'] ); ?><br><br>
			Order Notes: <?php echo esc_html( $d['order_notes'] ); ?>
		</td>
		<td colspan="3" style="border:1px solid #000; vertical-align:top; padding:10px;">
			<strong><u>Shop Details:</u></strong><br><br>
			Bank Name: <?php echo esc_html( $d['bank_name'] ); ?><br><br>
			Account Holder Name: <?php echo esc_html( $d['account_holder'] ); ?><br><br>
			Account Number: <?php echo esc_html( $d['account_number'] ); ?><br><br>
			IFSC Code: <?php echo esc_html( $d['ifsc'] ); ?><br><br>
			Branch: <?php echo esc_html( $d['branch'] ); ?><br><br>
			GPay / PhonePe / Paytm: <?php echo esc_html( $d['gpay_number'] ); ?>
		</td>
	</tr>

	<tr>
		<th style="width:30px;">S.No.</th>
		<th>Product Name</th>
		<th>Price</th>
		<th>Quantity</th>
		<th>Total</th>
	</tr>
	<?php echo $rows; ?>

	<tr>
		<td colspan="4" class="right bold">Sub Total:</td>
		<td><?php echo $this->money( $d['sub_total'] ); ?></td>
	</tr>
	<tr>
		<td colspan="2" class="right bold">Total Products: <?php echo intval( $d['total_products'] ); ?></td>
		<td class="right bold">Total Qty: <?php echo intval( $d['total_qty'] ); ?></td>
		<td class="right bold">Grand Total:</td>
		<td><?php echo $this->money( $d['grand_total'] ); ?></td>
	</tr>
	<tr>
		<td colspan="5" style="line-height:1.5;">
			<strong>Terms &amp; Conditions</strong>
			<ul style="font-family: DejaVu Sans, sans-serif;"><?php echo $terms_html; ?></ul>
			<div style="margin-top:10px; font-size:12px; text-align:center;">
				<strong>Thanks for your shopping!</strong><br>
				<?php if ( ! empty( $shop['website'] ) ) : ?>
				Visit us: <a href="<?php echo esc_url( $shop['website'] ); ?>"><?php echo esc_html( $shop['website'] ); ?></a>
				<?php endif; ?>
			</div>
		</td>
	</tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
