<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Converts a WC_Order into the flat data array used by WC_Estimate_PDF_Generator.
 */
class WC_Estimate_PDF_Order {

	public static function get_data( WC_Order $order ) {
		$items          = array();
		$total_products = 0;
		$total_qty      = 0;

		foreach ( $order->get_items() as $item ) {
			$qty        = $item->get_quantity();
			$line_total = (float) $item->get_total();
			$price      = $qty > 0 ? $line_total / $qty : $line_total;

			$items[] = array(
				'name'       => $item->get_name(),
				'price'      => $price,
				'qty'        => $qty,
				'line_total' => $line_total,
			);

			$total_products++;
			$total_qty += $qty;
		}

		$address_parts = array_filter( array(
			$order->get_billing_address_1(),
			$order->get_billing_address_2(),
			$order->get_billing_city(),
		) );

		$opts = get_option( 'wc_estimate_pdf_settings', array() );

		return array(
			'estimate_no'       => $order->get_order_number(),
			'date'              => $order->get_date_created() ? $order->get_date_created()->date( 'd-m-Y' ) : date( 'd-m-Y' ),
			'customer_name'     => trim( $order->get_formatted_billing_full_name() ),
			'customer_phone'    => $order->get_billing_phone(),
			'customer_email'    => $order->get_billing_email(),
			'customer_address'  => implode( ', ', $address_parts ),
			'customer_state'    => $order->get_billing_state(),
			'customer_district' => $order->get_billing_city(),
			'order_notes'       => $order->get_customer_note() ? $order->get_customer_note() : '-',
			'items'             => $items,
			'sub_total'         => (float) $order->get_subtotal(),
			'total_products'    => $total_products,
			'total_qty'         => $total_qty,
			'grand_total'       => (float) $order->get_total(),
			'bank_name'         => isset( $opts['bank_name'] ) ? $opts['bank_name'] : '',
			'account_holder'    => isset( $opts['account_holder'] ) ? $opts['account_holder'] : '',
			'account_number'    => isset( $opts['account_number'] ) ? $opts['account_number'] : '',
			'ifsc'              => isset( $opts['ifsc'] ) ? $opts['ifsc'] : '',
			'branch'            => isset( $opts['branch'] ) ? $opts['branch'] : '',
			'gpay_number'       => isset( $opts['gpay_number'] ) ? $opts['gpay_number'] : '',
			'terms'             => self::get_terms( $opts ),
		);
	}

	protected static function get_terms( $opts ) {
		if ( ! empty( $opts['terms'] ) ) {
			return array_filter( array_map( 'trim', explode( "\n", $opts['terms'] ) ) );
		}
		// Sensible Sivakasi-fireworks-retail defaults; edit in the settings screen.
		return array(
			'F.O.R. Sivakasi - Lorry Freight Extra To Pay.',
			'The above price list includes 18% GST and is based on market value.',
			'No Cash on Delivery, No Free Delivery, No Door Delivery. Only for goods received at the transport office.',
			'Minimum Order Value - Tamil Nadu: ₹3,000/-; Karnataka, Andhra, Kerala, Telangana: ₹5,000/- + 3% Packing & Forwarding; Rest of India: ₹25,000/- + 3% Packing & Forwarding.',
			'Product quality may vary depending on natural conditions.',
			'We usually provide 95% to 99% assured quality products.',
		);
	}
}
