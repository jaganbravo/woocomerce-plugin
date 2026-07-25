<?php
/**
 * Admin page for managing support requests (feature requests + failed questions).
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Dataviz_AI_Support_Requests_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'support_request',
			'plural'   => 'support_requests',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'id'           => __( 'ID', 'dataviz-ai-woocommerce' ),
			'type'         => __( 'Type', 'dataviz-ai-woocommerce' ),
			'question'     => __( 'Question', 'dataviz-ai-woocommerce' ),
			'entity_type'  => __( 'Entity', 'dataviz-ai-woocommerce' ),
			'error_reason' => __( 'Error / Reason', 'dataviz-ai-woocommerce' ),
			'user_name'    => __( 'User', 'dataviz-ai-woocommerce' ),
			'status'       => __( 'Status', 'dataviz-ai-woocommerce' ),
			'vote_count'   => __( 'Votes', 'dataviz-ai-woocommerce' ),
			'created_at'   => __( 'Created', 'dataviz-ai-woocommerce' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'id'         => array( 'id', false ),
			'type'       => array( 'type', false ),
			'status'     => array( 'status', false ),
			'vote_count' => array( 'vote_count', true ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="request_ids[]" value="%d" />', $item['id'] );
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	protected function column_id( $item ) {
		return '#' . absint( $item['id'] );
	}

	protected function column_type( $item ) {
		$labels = array(
			'feature_request' => '<span class="dataviz-sr-badge dataviz-sr-badge--feature">' . esc_html__( 'Feature Request', 'dataviz-ai-woocommerce' ) . '</span>',
			'failed_question' => '<span class="dataviz-sr-badge dataviz-sr-badge--failed">' . esc_html__( 'Failed Question', 'dataviz-ai-woocommerce' ) . '</span>',
		);
		return $labels[ $item['type'] ] ?? esc_html( $item['type'] );
	}

	protected function column_question( $item ) {
		$question = esc_html( wp_trim_words( $item['question'] ?? '', 12, '...' ) );
		$actions  = array();

		$detail_data = array(
			'question'     => $item['question'] ?? '',
			'error_reason' => $item['error_reason'] ?? '',
			'description'  => $item['description'] ?? '',
			'raw_intent'   => $item['raw_intent'] ?? '',
			'entity_type'  => $item['entity_type'] ?? '',
			'user_name'    => $item['user_name'] ?? '',
			'created_at'   => $item['created_at'] ?? '',
		);

		$actions['view_details'] = sprintf(
			'<a href="#" class="dataviz-sr-view-details" data-id="%d" data-detail="%s">%s</a>',
			$item['id'],
			esc_attr( wp_json_encode( $detail_data ) ),
			esc_html__( 'View Details', 'dataviz-ai-woocommerce' )
		);

		if ( $item['status'] === 'pending' ) {
			$resolve_url = wp_nonce_url(
				add_query_arg( array( 'action' => 'resolve', 'request_id' => $item['id'] ) ),
				'dataviz_sr_action'
			);
			$wontfix_url = wp_nonce_url(
				add_query_arg( array( 'action' => 'wont_fix', 'request_id' => $item['id'] ) ),
				'dataviz_sr_action'
			);
			$actions['resolve']  = sprintf( '<a href="%s">%s</a>', esc_url( $resolve_url ), esc_html__( 'Resolve', 'dataviz-ai-woocommerce' ) );
			$actions['wont_fix'] = sprintf( '<a href="%s" class="dataviz-sr-wontfix">%s</a>', esc_url( $wontfix_url ), esc_html__( "Won't Fix", 'dataviz-ai-woocommerce' ) );
		} elseif ( $item['status'] !== 'pending' ) {
			$reopen_url = wp_nonce_url(
				add_query_arg( array( 'action' => 'reopen', 'request_id' => $item['id'] ) ),
				'dataviz_sr_action'
			);
			$actions['reopen'] = sprintf( '<a href="%s">%s</a>', esc_url( $reopen_url ), esc_html__( 'Re-open', 'dataviz-ai-woocommerce' ) );
		}

		return $question . $this->row_actions( $actions );
	}

	protected function column_error_reason( $item ) {
		$reason = $item['error_reason'] ?? '';
		if ( empty( $reason ) ) {
			return '&mdash;';
		}
		return '<span title="' . esc_attr( $reason ) . '">' . esc_html( wp_trim_words( $reason, 8, '...' ) ) . '</span>';
	}

	protected function column_status( $item ) {
		$map = array(
			'pending'  => '<span class="dataviz-sr-status dataviz-sr-status--pending">' . esc_html__( 'Pending', 'dataviz-ai-woocommerce' ) . '</span>',
			'resolved' => '<span class="dataviz-sr-status dataviz-sr-status--resolved">' . esc_html__( 'Resolved', 'dataviz-ai-woocommerce' ) . '</span>',
			'wont_fix' => '<span class="dataviz-sr-status dataviz-sr-status--wontfix">' . esc_html__( "Won't Fix", 'dataviz-ai-woocommerce' ) . '</span>',
		);
		return $map[ $item['status'] ] ?? esc_html( $item['status'] );
	}

	protected function column_created_at( $item ) {
		$date = $item['created_at'] ?? '';
		if ( empty( $date ) ) {
			return '&mdash;';
		}
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) );
	}

	protected function get_bulk_actions() {
		return array(
			'bulk_resolve'  => __( 'Mark Resolved', 'dataviz-ai-woocommerce' ),
			'bulk_wont_fix' => __( "Mark Won't Fix", 'dataviz-ai-woocommerce' ),
			'bulk_reopen'   => __( 'Re-open', 'dataviz-ai-woocommerce' ),
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_type   = isset( $_GET['req_type'] ) ? sanitize_text_field( wp_unslash( $_GET['req_type'] ) ) : 'all';
		$current_status = isset( $_GET['req_status'] ) ? sanitize_text_field( wp_unslash( $_GET['req_status'] ) ) : 'all';

		echo '<div class="alignleft actions">';

		echo '<select name="req_type">';
		printf( '<option value="all" %s>%s</option>', selected( $current_type, 'all', false ), esc_html__( 'All Types', 'dataviz-ai-woocommerce' ) );
		printf( '<option value="feature_request" %s>%s</option>', selected( $current_type, 'feature_request', false ), esc_html__( 'Feature Requests', 'dataviz-ai-woocommerce' ) );
		printf( '<option value="failed_question" %s>%s</option>', selected( $current_type, 'failed_question', false ), esc_html__( 'Failed Questions', 'dataviz-ai-woocommerce' ) );
		echo '</select>';

		echo '<select name="req_status">';
		printf( '<option value="all" %s>%s</option>', selected( $current_status, 'all', false ), esc_html__( 'All Statuses', 'dataviz-ai-woocommerce' ) );
		printf( '<option value="pending" %s>%s</option>', selected( $current_status, 'pending', false ), esc_html__( 'Pending', 'dataviz-ai-woocommerce' ) );
		printf( '<option value="resolved" %s>%s</option>', selected( $current_status, 'resolved', false ), esc_html__( 'Resolved', 'dataviz-ai-woocommerce' ) );
		printf( '<option value="wont_fix" %s>%s</option>', selected( $current_status, 'wont_fix', false ), esc_html__( "Won't Fix", 'dataviz-ai-woocommerce' ) );
		echo '</select>';

		submit_button( __( 'Filter', 'dataviz-ai-woocommerce' ), '', 'filter_action', false );
		echo '</div>';
	}

	public function prepare_items() {
		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$type    = isset( $_GET['req_type'] ) ? sanitize_text_field( wp_unslash( $_GET['req_type'] ) ) : 'all';
		$status  = isset( $_GET['req_status'] ) ? sanitize_text_field( wp_unslash( $_GET['req_status'] ) ) : 'all';
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';

		$this->items = Dataviz_AI_Support_Requests::query( array(
			'type'    => $type,
			'status'  => $status,
			'search'  => $search,
			'orderby' => $orderby,
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => $offset,
		) );

		$total = Dataviz_AI_Support_Requests::count( $type, $status );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}
}

// ---------------------------------------------------------------------------
// Page renderer + action handling
// ---------------------------------------------------------------------------

class Dataviz_AI_Support_Requests_Admin {

	const MENU_SLUG = 'dataviz-ai-support-requests';

	public static function register_submenu() {
		add_submenu_page(
			'dataviz-ai-woocommerce',
			__( 'Support & Requests', 'dataviz-ai-woocommerce' ),
			__( 'Support & Requests', 'dataviz-ai-woocommerce' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'dataviz-ai-support-requests',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/css/support-requests.css',
			array(),
			DATAVIZ_AI_WC_VERSION
		);
	}

	public static function process_actions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Single row actions.
		if ( isset( $_GET['action'], $_GET['request_id'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dataviz_sr_action' ) ) {
			$action = sanitize_key( $_GET['action'] );
			$id     = absint( $_GET['request_id'] );

			$status_map = array(
				'resolve'  => Dataviz_AI_Support_Requests::STATUS_RESOLVED,
				'wont_fix' => Dataviz_AI_Support_Requests::STATUS_WONT_FIX,
				'reopen'   => Dataviz_AI_Support_Requests::STATUS_PENDING,
			);

			if ( isset( $status_map[ $action ] ) ) {
				Dataviz_AI_Support_Requests::update_status( $id, $status_map[ $action ] );
				$redirect = remove_query_arg( array( 'action', 'request_id', '_wpnonce' ) );
				$redirect = add_query_arg( 'sr_updated', '1', $redirect );
				wp_safe_redirect( $redirect );
				exit;
			}
		}

		// Bulk actions.
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-support_requests' ) ) {
			$bulk_action = '';
			if ( ! empty( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
				$bulk_action = sanitize_key( $_POST['action'] );
			} elseif ( ! empty( $_POST['action2'] ) && $_POST['action2'] !== '-1' ) {
				$bulk_action = sanitize_key( $_POST['action2'] );
			}

			$ids = isset( $_POST['request_ids'] ) ? array_map( 'absint', (array) $_POST['request_ids'] ) : array();

			if ( $bulk_action && ! empty( $ids ) ) {
				$status_map = array(
					'bulk_resolve'  => Dataviz_AI_Support_Requests::STATUS_RESOLVED,
					'bulk_wont_fix' => Dataviz_AI_Support_Requests::STATUS_WONT_FIX,
					'bulk_reopen'   => Dataviz_AI_Support_Requests::STATUS_PENDING,
				);

				if ( isset( $status_map[ $bulk_action ] ) ) {
					$count = Dataviz_AI_Support_Requests::bulk_update_status( $ids, $status_map[ $bulk_action ] );
					$redirect = add_query_arg( 'sr_updated', (string) $count, remove_query_arg( array( 'action', 'action2' ) ) );
					wp_safe_redirect( $redirect );
					exit;
				}
			}
		}
	}

	public static function render_page() {
		self::process_actions();

		$table = new Dataviz_AI_Support_Requests_Table();
		$table->prepare_items();

		$total_pending  = Dataviz_AI_Support_Requests::count( 'all', 'pending' );
		$total_features = Dataviz_AI_Support_Requests::count( 'feature_request' );
		$total_failed   = Dataviz_AI_Support_Requests::count( 'failed_question' );
		?>
		<div class="wrap dataviz-sr-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Support & Requests', 'dataviz-ai-woocommerce' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['sr_updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Request(s) updated successfully.', 'dataviz-ai-woocommerce' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="dataviz-sr-summary">
				<div class="dataviz-sr-summary__card">
					<span class="dataviz-sr-summary__number"><?php echo esc_html( $total_pending ); ?></span>
					<span class="dataviz-sr-summary__label"><?php esc_html_e( 'Pending', 'dataviz-ai-woocommerce' ); ?></span>
				</div>
				<div class="dataviz-sr-summary__card">
					<span class="dataviz-sr-summary__number"><?php echo esc_html( $total_features ); ?></span>
					<span class="dataviz-sr-summary__label"><?php esc_html_e( 'Feature Requests', 'dataviz-ai-woocommerce' ); ?></span>
				</div>
				<div class="dataviz-sr-summary__card">
					<span class="dataviz-sr-summary__number"><?php echo esc_html( $total_failed ); ?></span>
					<span class="dataviz-sr-summary__label"><?php esc_html_e( 'Failed Questions', 'dataviz-ai-woocommerce' ); ?></span>
				</div>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search Requests', 'dataviz-ai-woocommerce' ), 'dataviz-sr-search' );
				$table->display();
				?>
			</form>
		</div>

		<div id="dataviz-sr-detail-modal" class="dataviz-sr-modal" style="display:none;">
			<div class="dataviz-sr-modal__backdrop"></div>
			<div class="dataviz-sr-modal__content">
				<div class="dataviz-sr-modal__header">
					<h2><?php esc_html_e( 'Request Details', 'dataviz-ai-woocommerce' ); ?></h2>
					<button type="button" class="dataviz-sr-modal__close">&times;</button>
				</div>
				<div class="dataviz-sr-modal__body dataviz-sr-detail-body"></div>
			</div>
		</div>

		<script>
		(function(){
			function escHtml(s) {
				var d = document.createElement('div');
				d.textContent = s;
				return d.innerHTML;
			}
			function buildSection(label, value, isCode) {
				if (!value) return '';
				var content;
				if (isCode) {
					try { content = '<pre class="dataviz-sr-detail-code">' + escHtml(JSON.stringify(JSON.parse(value), null, 2)) + '</pre>'; }
					catch(_) { content = '<pre class="dataviz-sr-detail-code">' + escHtml(value) + '</pre>'; }
				} else {
					content = '<p class="dataviz-sr-detail-text">' + escHtml(value) + '</p>';
				}
				return '<div class="dataviz-sr-detail-section"><strong>' + escHtml(label) + '</strong>' + content + '</div>';
			}

			document.querySelectorAll('.dataviz-sr-view-details').forEach(function(el){
				el.addEventListener('click', function(e){
					e.preventDefault();
					var modal = document.getElementById('dataviz-sr-detail-modal');
					var body  = modal.querySelector('.dataviz-sr-detail-body');
					var data  = JSON.parse(this.dataset.detail);
					var html  = '';
					html += buildSection('<?php echo esc_js( __( 'Question', 'dataviz-ai-woocommerce' ) ); ?>', data.question);
					html += buildSection('<?php echo esc_js( __( 'Error / Reason', 'dataviz-ai-woocommerce' ) ); ?>', data.error_reason);
					html += buildSection('<?php echo esc_js( __( 'Description', 'dataviz-ai-woocommerce' ) ); ?>', data.description);
					html += buildSection('<?php echo esc_js( __( 'Entity Type', 'dataviz-ai-woocommerce' ) ); ?>', data.entity_type);
					html += buildSection('<?php echo esc_js( __( 'User', 'dataviz-ai-woocommerce' ) ); ?>', data.user_name);
					html += buildSection('<?php echo esc_js( __( 'Created', 'dataviz-ai-woocommerce' ) ); ?>', data.created_at);
					html += buildSection('<?php echo esc_js( __( 'Raw Intent JSON', 'dataviz-ai-woocommerce' ) ); ?>', data.raw_intent, true);
					body.innerHTML = html || '<p><?php echo esc_js( __( 'No details available.', 'dataviz-ai-woocommerce' ) ); ?></p>';
					modal.style.display = 'flex';
				});
			});

			var modal = document.getElementById('dataviz-sr-detail-modal');
			if (modal) {
				modal.querySelector('.dataviz-sr-modal__close').addEventListener('click', function(){ modal.style.display = 'none'; });
				modal.querySelector('.dataviz-sr-modal__backdrop').addEventListener('click', function(){ modal.style.display = 'none'; });
			}
		})();
		</script>
		<?php
	}
}
