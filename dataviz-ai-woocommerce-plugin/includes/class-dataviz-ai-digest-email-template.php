<?php
/**
 * Renders the HTML email for a scheduled digest.
 *
 * Uses inline styles for maximum email-client compatibility.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Digest_Email_Template {

	/**
	 * Render the full email HTML from digest data.
	 *
	 * @param array $data Output from Dataviz_AI_Digest_Generator::build().
	 * @return string HTML email body.
	 */
	public static function render( array $data ) {
		$meta      = $data['meta'] ?? array();
		$name      = esc_html( $meta['digest_name'] ?? 'Store Digest' );
		$from      = esc_html( $meta['date_from'] ?? '' );
		$to        = esc_html( $meta['date_to'] ?? '' );
		$freq      = ucfirst( esc_html( $meta['frequency'] ?? 'weekly' ) );
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url() );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;">
<tr><td align="center" style="padding:30px 10px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">

<!-- Header -->
<tr>
<td style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:28px 32px;text-align:center;">
	<h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:600;"><?php echo $name; ?></h1>
	<p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:13px;"><?php echo $site_name; ?> &mdash; <?php echo $freq; ?> report for <?php echo $from; ?> to <?php echo $to; ?></p>
</td>
</tr>

<!-- Body -->
<tr>
<td style="padding:24px 32px;">

<?php if ( isset( $data['revenue_summary'] ) ) : ?>
<?php self::render_revenue_summary( $data['revenue_summary'] ); ?>
<?php endif; ?>

<?php if ( isset( $data['order_breakdown'] ) && ! empty( $data['order_breakdown'] ) ) : ?>
<?php self::render_order_breakdown( $data['order_breakdown'] ); ?>
<?php endif; ?>

<?php if ( isset( $data['top_products'] ) && ! empty( $data['top_products'] ) ) : ?>
<?php self::render_top_products( $data['top_products'] ); ?>
<?php endif; ?>

<?php if ( isset( $data['top_customers'] ) && ! empty( $data['top_customers'] ) ) : ?>
<?php self::render_top_customers( $data['top_customers'] ); ?>
<?php endif; ?>

<?php if ( isset( $data['low_stock'] ) && ! empty( $data['low_stock'] ) ) : ?>
<?php self::render_low_stock( $data['low_stock'] ); ?>
<?php endif; ?>

<?php if ( isset( $data['refund_summary'] ) ) : ?>
<?php self::render_refund_summary( $data['refund_summary'] ); ?>
<?php endif; ?>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="background:#f9fafb;padding:20px 32px;text-align:center;border-top:1px solid #e5e7eb;">
	<p style="margin:0;color:#9ca3af;font-size:12px;">
		Sent by <a href="<?php echo $site_url; ?>" style="color:#6366f1;text-decoration:none;"><?php echo $site_name; ?></a> via Dataviz AI for WooCommerce.
		<br>You can manage your digest schedules in the WordPress admin.
	</p>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Section renderers
	// ------------------------------------------------------------------

	private static function render_revenue_summary( array $d ) {
		$revenue    = wc_price( $d['total_revenue'] ?? 0 );
		$orders     = (int) ( $d['total_orders'] ?? 0 );
		$avg        = wc_price( $d['avg_order_value'] ?? 0 );
		$customers  = (int) ( $d['unique_customers'] ?? 0 );
		?>
		<?php self::section_heading( __( 'Revenue Summary', 'dataviz-ai-woocommerce' ) ); ?>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
		<tr>
			<?php self::metric_card( __( 'Revenue', 'dataviz-ai-woocommerce' ), $revenue, '#10b981' ); ?>
			<?php self::metric_card( __( 'Orders', 'dataviz-ai-woocommerce' ), $orders, '#6366f1' ); ?>
			<?php self::metric_card( __( 'Avg Order', 'dataviz-ai-woocommerce' ), $avg, '#f59e0b' ); ?>
			<?php self::metric_card( __( 'Customers', 'dataviz-ai-woocommerce' ), $customers, '#ec4899' ); ?>
		</tr>
		</table>
		<?php
	}

	private static function render_order_breakdown( array $rows ) {
		?>
		<?php self::section_heading( __( 'Order Status Breakdown', 'dataviz-ai-woocommerce' ) ); ?>
		<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-bottom:24px;border:1px solid #e5e7eb;border-radius:6px;border-collapse:collapse;">
		<tr style="background:#f9fafb;">
			<th align="left" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Status', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Count', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Revenue', 'dataviz-ai-woocommerce' ); ?></th>
		</tr>
		<?php foreach ( $rows as $row ) : ?>
		<tr>
			<td style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html( ucfirst( $row['status'] ?? '—' ) ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo (int) ( $row['count'] ?? 0 ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo wc_price( $row['revenue'] ?? 0 ); ?></td>
		</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}

	private static function render_top_products( array $rows ) {
		?>
		<?php self::section_heading( __( 'Top Products', 'dataviz-ai-woocommerce' ) ); ?>
		<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-bottom:24px;border:1px solid #e5e7eb;border-radius:6px;border-collapse:collapse;">
		<tr style="background:#f9fafb;">
			<th align="left" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Product', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Sold', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Revenue', 'dataviz-ai-woocommerce' ); ?></th>
		</tr>
		<?php foreach ( $rows as $row ) : ?>
		<tr>
			<td style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html( $row['name'] ?? $row['category'] ?? '—' ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo (int) ( $row['quantity_sold'] ?? $row['order_count'] ?? 0 ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo wc_price( $row['total_revenue'] ?? $row['revenue'] ?? 0 ); ?></td>
		</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}

	private static function render_top_customers( array $data ) {
		$customers = isset( $data['customers'] ) ? $data['customers'] : $data;
		if ( empty( $customers ) ) {
			return;
		}
		?>
		<?php self::section_heading( __( 'Top Customers', 'dataviz-ai-woocommerce' ) ); ?>
		<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-bottom:24px;border:1px solid #e5e7eb;border-radius:6px;border-collapse:collapse;">
		<tr style="background:#f9fafb;">
			<th align="left" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Customer', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Orders', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Spent', 'dataviz-ai-woocommerce' ); ?></th>
		</tr>
		<?php foreach ( $customers as $c ) : ?>
		<tr>
			<td style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html( $c['name'] ?? ( '#' . ( $c['id'] ?? '?' ) ) ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo (int) ( $c['order_count'] ?? 0 ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;"><?php echo wc_price( $c['total_spent'] ?? 0 ); ?></td>
		</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}

	private static function render_low_stock( array $products ) {
		if ( empty( $products ) ) {
			return;
		}
		?>
		<?php self::section_heading( __( 'Low-Stock Alerts', 'dataviz-ai-woocommerce' ) ); ?>
		<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="margin-bottom:24px;border:1px solid #e5e7eb;border-radius:6px;border-collapse:collapse;">
		<tr style="background:#f9fafb;">
			<th align="left" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Product', 'dataviz-ai-woocommerce' ); ?></th>
			<th align="right" style="padding:8px 12px;font-size:12px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;"><?php esc_html_e( 'Stock Qty', 'dataviz-ai-woocommerce' ); ?></th>
		</tr>
		<?php foreach ( $products as $p ) : ?>
		<tr>
			<td style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;color:#ef4444;font-weight:500;"><?php echo esc_html( $p['name'] ?? '—' ); ?></td>
			<td align="right" style="padding:8px 12px;font-size:14px;border-bottom:1px solid #f3f4f6;color:#ef4444;font-weight:600;"><?php echo (int) ( $p['stock_quantity'] ?? 0 ); ?></td>
		</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}

	private static function render_refund_summary( array $d ) {
		$count  = (int) ( $d['refund_count'] ?? 0 );
		$amount = wc_price( $d['total_refunded'] ?? 0 );
		?>
		<?php self::section_heading( __( 'Refund Summary', 'dataviz-ai-woocommerce' ) ); ?>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
		<tr>
			<?php self::metric_card( __( 'Refunds', 'dataviz-ai-woocommerce' ), $count, '#ef4444' ); ?>
			<?php self::metric_card( __( 'Amount', 'dataviz-ai-woocommerce' ), $amount, '#ef4444' ); ?>
			<td width="25%"></td>
			<td width="25%"></td>
		</tr>
		</table>
		<?php
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private static function section_heading( $title ) {
		?>
		<h2 style="margin:20px 0 10px;font-size:16px;font-weight:600;color:#1f2937;border-bottom:2px solid #e5e7eb;padding-bottom:6px;"><?php echo esc_html( $title ); ?></h2>
		<?php
	}

	private static function metric_card( $label, $value, $color ) {
		?>
		<td width="25%" style="padding:4px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
			<tr><td style="padding:12px;text-align:center;">
				<span style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:4px;"><?php echo esc_html( $label ); ?></span>
				<span style="display:block;font-size:20px;font-weight:700;color:<?php echo esc_attr( $color ); ?>;"><?php echo $value; ?></span>
			</td></tr>
			</table>
		</td>
		<?php
	}
}
