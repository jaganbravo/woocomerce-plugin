<?php
/**
 * Admin list of admin-chat thumbs feedback + email to vendor.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table for chat feedback rows.
 */
class Dataviz_AI_Chat_Feedback_Table extends WP_List_Table {

	/** @var Dataviz_AI_Chat_History */
	protected $chat_history;

	public function __construct( Dataviz_AI_Chat_History $chat_history ) {
		$this->chat_history = $chat_history;
		parent::__construct( array(
			'singular' => 'chat_feedback',
			'plural'   => 'chat_feedback_items',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'              => '<input type="checkbox" />',
			'id'              => __( 'ID', 'dataviz-ai-woocommerce' ),
			'feedback_vote'   => __( 'Vote', 'dataviz-ai-woocommerce' ),
			'user_id'         => __( 'User', 'dataviz-ai-woocommerce' ),
			'message_content' => __( 'Assistant reply', 'dataviz-ai-woocommerce' ),
			'feedback_reason' => __( 'Reason', 'dataviz-ai-woocommerce' ),
			'feedback_note'   => __( 'Note', 'dataviz-ai-woocommerce' ),
			'feedback_at'     => __( 'Feedback', 'dataviz-ai-woocommerce' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="feedback_ids[]" value="%d" />', absint( $item['id'] ?? 0 ) );
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	protected function column_id( $item ) {
		$id = absint( $item['id'] ?? 0 );
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => Dataviz_AI_Chat_Feedback_Admin::MENU_SLUG,
					'action'     => 'email_vendor',
					'message_id' => $id,
				),
				admin_url( 'admin.php' )
			),
			'dataviz_cf_action'
		);
		$actions = array(
			'email_vendor' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Email to vendor', 'dataviz-ai-woocommerce' )
			),
		);
		return '#' . $id . $this->row_actions( $actions );
	}

	protected function column_feedback_vote( $item ) {
		$v = $item['feedback_vote'] ?? '';
		if ( 'up' === $v ) {
			return '<span class="dataviz-sr-status dataviz-sr-status--resolved">' . esc_html__( 'Helpful', 'dataviz-ai-woocommerce' ) . '</span>';
		}
		if ( 'down' === $v ) {
			return '<span class="dataviz-sr-status dataviz-sr-status--wontfix">' . esc_html__( 'Not helpful', 'dataviz-ai-woocommerce' ) . '</span>';
		}
		return esc_html( $v );
	}

	protected function column_user_id( $item ) {
		$uid = absint( $item['user_id'] ?? 0 );
		if ( ! $uid ) {
			return '&mdash;';
		}
		$user = get_userdata( $uid );
		if ( ! $user ) {
			return (string) $uid;
		}
		$edit = get_edit_user_link( $uid );
		if ( $edit ) {
			return '<a href="' . esc_url( $edit ) . '">' . esc_html( $user->display_name ) . '</a>';
		}
		return esc_html( $user->display_name );
	}

	protected function column_message_content( $item ) {
		$text = wp_strip_all_tags( (string) ( $item['message_content'] ?? '' ) );
		return esc_html( wp_trim_words( $text, 18, '…' ) );
	}

	protected function column_feedback_reason( $item ) {
		$r = $item['feedback_reason'] ?? '';
		return $r !== '' ? esc_html( $r ) : '&mdash;';
	}

	protected function column_feedback_note( $item ) {
		$n = wp_strip_all_tags( (string) ( $item['feedback_note'] ?? '' ) );
		return $n !== '' ? esc_html( wp_trim_words( $n, 12, '…' ) ) : '&mdash;';
	}

	protected function column_feedback_at( $item ) {
		$d = $item['feedback_at'] ?? '';
		if ( empty( $d ) ) {
			return '&mdash;';
		}
		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $d ) ) );
	}

	protected function get_bulk_actions() {
		return array(
			'bulk_email_vendor' => __( 'Email to vendor', 'dataviz-ai-woocommerce' ),
		);
	}

	public function prepare_items() {
		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$this->items = $this->chat_history->get_feedback_entries_admin( array(
			'limit'  => $per_page,
			'offset' => $offset,
			'search' => $search,
		) );

		$total = $this->chat_history->count_feedback_entries_admin( $search );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array(),
		);
	}
}

/**
 * Menu and handlers.
 */
class Dataviz_AI_Chat_Feedback_Admin {

	const MENU_SLUG = 'dataviz-ai-chat-feedback';

	/**
	 * Error code → message (mirrors Support & Requests pattern).
	 *
	 * @param string $code WP_Error code from email_feedback_to_vendor.
	 * @return string
	 */
	protected static function email_error_message( $code ) {
		$messages = array(
			'dataviz_cf_email_no_vendor' => __( 'Set a vendor support email under Support & Requests first.', 'dataviz-ai-woocommerce' ),
			'dataviz_cf_not_found'       => __( 'Message not found.', 'dataviz-ai-woocommerce' ),
			'dataviz_cf_no_feedback'     => __( 'This row has no feedback to send.', 'dataviz-ai-woocommerce' ),
			'dataviz_cf_email_failed'    => __( 'WordPress could not send email.', 'dataviz-ai-woocommerce' ),
		);

		return $messages[ $code ] ?? __( 'Could not send email.', 'dataviz-ai-woocommerce' );
	}

	public static function register_submenu() {
		add_submenu_page(
			'dataviz-ai-woocommerce',
			__( 'Chat feedback', 'dataviz-ai-woocommerce' ),
			__( 'Chat feedback', 'dataviz-ai-woocommerce' ),
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

		$history = new Dataviz_AI_Chat_History();

		if ( isset( $_GET['page'], $_GET['action'], $_GET['message_id'], $_GET['_wpnonce'] )
			&& self::MENU_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'email_vendor' === sanitize_key( wp_unslash( $_GET['action'] ) )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dataviz_cf_action' ) ) {
			$id       = absint( $_GET['message_id'] );
			$result   = $history->email_feedback_to_vendor( $id );
			$redirect = wp_get_referer();
			if ( ! $redirect ) {
				$redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
			}
			$redirect = remove_query_arg( array( 'action', 'message_id', '_wpnonce', 'cf_emailed', 'cf_email_err' ), $redirect );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( 'cf_email_err', $result->get_error_code(), $redirect ) );
			} else {
				wp_safe_redirect( add_query_arg( 'cf_emailed', '1', $redirect ) );
			}
			exit;
		}

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-chat_feedback_items' ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) && empty( $_POST['action2'] ) ) {
			return;
		}

		$bulk = ! empty( $_POST['action'] ) && '-1' !== $_POST['action']
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: sanitize_key( wp_unslash( $_POST['action2'] ?? '' ) );

		if ( 'bulk_email_vendor' !== $bulk ) {
			return;
		}

		$ids = isset( $_POST['feedback_ids'] ) ? array_map( 'absint', (array) $_POST['feedback_ids'] ) : array();
		if ( empty( $ids ) ) {
			return;
		}

		$emailed = 0;
		$failed  = 0;
		foreach ( $ids as $mid ) {
			$r = $history->email_feedback_to_vendor( $mid );
			if ( is_wp_error( $r ) ) {
				++$failed;
			} else {
				++$emailed;
			}
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		}
		$redirect = remove_query_arg( array( 'action', 'action2', 'cf_bulk_emailed', 'cf_bulk_failed' ), $redirect );
		wp_safe_redirect( add_query_arg(
			array(
				'cf_bulk_emailed' => (string) $emailed,
				'cf_bulk_failed'  => (string) $failed,
			),
			$redirect
		) );
		exit;
	}

	public static function render_page() {
		self::process_actions();

		$table = new Dataviz_AI_Chat_Feedback_Table( new Dataviz_AI_Chat_History() );
		$table->prepare_items();

		$support_url  = admin_url( 'admin.php?page=' . Dataviz_AI_Support_Requests_Admin::MENU_SLUG );
		$vendor_email = Dataviz_AI_Support_Requests::get_vendor_support_email();
		?>
		<div class="wrap dataviz-sr-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Chat feedback', 'dataviz-ai-woocommerce' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['cf_emailed'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Feedback was emailed to the vendor address.', 'dataviz-ai-woocommerce' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $_GET['cf_email_err'] ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( self::email_error_message( sanitize_key( wp_unslash( $_GET['cf_email_err'] ) ) ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['cf_bulk_emailed'] ) || isset( $_GET['cf_bulk_failed'] ) ) : ?>
				<?php
				$ok  = isset( $_GET['cf_bulk_emailed'] ) ? absint( $_GET['cf_bulk_emailed'] ) : 0;
				$bad = isset( $_GET['cf_bulk_failed'] ) ? absint( $_GET['cf_bulk_failed'] ) : 0;
				?>
				<div class="notice <?php echo $bad ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
					<p>
						<?php
						printf(
							/* translators: 1: emails sent, 2: failures */
							esc_html__( 'Email to vendor: %1$d sent, %2$d failed.', 'dataviz-ai-woocommerce' ),
							$ok,
							$bad
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="dataviz-sr-vendor-email card" style="max-width: 720px; margin: 1rem 0 1.5rem; padding: 1rem 1.25rem;">
				<h2 style="margin-top: 0;"><?php esc_html_e( 'Vendor inbox', 'dataviz-ai-woocommerce' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Thumbs feedback from the Dataviz AI admin chat is listed below. Use “Email to vendor” to forward entries to your plugin support team.', 'dataviz-ai-woocommerce' ); ?>
				</p>
				<p>
					<?php
					if ( is_email( $vendor_email ) ) {
						printf(
							/* translators: %s: email address */
							esc_html__( 'Vendor address: %s', 'dataviz-ai-woocommerce' ),
							'<strong>' . esc_html( $vendor_email ) . '</strong>'
						);
					} else {
						esc_html_e( 'No vendor email is set yet.', 'dataviz-ai-woocommerce' );
					}
					?>
					&nbsp;
					<a href="<?php echo esc_url( $support_url ); ?>"><?php esc_html_e( 'Configure on Support & Requests →', 'dataviz-ai-woocommerce' ); ?></a>
				</p>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search feedback', 'dataviz-ai-woocommerce' ), 'dataviz-cf-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
