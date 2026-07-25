<?php
/**
 * Admin page for managing scheduled email digests.
 *
 * @package Dataviz_AI_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dataviz_AI_Digest_Admin {

	/**
	 * @var Dataviz_AI_Digest_Generator
	 */
	private $generator;

	public function __construct( Dataviz_AI_Digest_Generator $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Register the submenu page.
	 */
	public function register_submenu() {
		add_submenu_page(
			'dataviz-ai-woocommerce',
			__( 'Email Digests', 'dataviz-ai-woocommerce' ),
			__( 'Email Digests', 'dataviz-ai-woocommerce' ),
			'manage_woocommerce',
			'dataviz-ai-digests',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets on the digest admin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'dataviz-ai-digests' ) ) {
			return;
		}
		wp_enqueue_style(
			'dataviz-ai-digest-admin',
			DATAVIZ_AI_WC_PLUGIN_URL . 'admin/css/digest-admin.css',
			array(),
			DATAVIZ_AI_WC_VERSION
		);
	}

	/**
	 * Route to the correct sub-view.
	 */
	public function render_page() {
		$this->process_actions();

		// phpcs:ignore WordPress.Security.NonceVerification
		$action = isset( $_GET['digest_action'] ) ? sanitize_text_field( wp_unslash( $_GET['digest_action'] ) ) : 'list';

		switch ( $action ) {
			case 'new':
			case 'edit':
				$this->render_form();
				break;
			case 'preview':
				$this->render_preview();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	// ------------------------------------------------------------------
	// Actions
	// ------------------------------------------------------------------

	private function process_actions() {
		if ( ! isset( $_POST['dataviz_digest_nonce'] ) && ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		// Save / update form.
		if ( isset( $_POST['dataviz_digest_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dataviz_digest_nonce'] ) ), 'dataviz_digest_save' ) ) {
			$this->handle_save();
			return;
		}

		// GET actions (delete, pause, activate, send_now).
		if ( isset( $_GET['_wpnonce'], $_GET['digest_action'], $_GET['digest_id'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['digest_action'] ) );
			$id     = (int) $_GET['digest_id'];

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dataviz_digest_' . $action . '_' . $id ) ) {
				return;
			}

			switch ( $action ) {
				case 'delete':
					Dataviz_AI_Email_Digests::delete( $id );
					$this->redirect_with_notice( 'deleted' );
					break;
				case 'pause':
					Dataviz_AI_Email_Digests::update( $id, array( 'status' => 'paused' ) );
					$this->redirect_with_notice( 'paused' );
					break;
				case 'activate':
					Dataviz_AI_Email_Digests::update( $id, array( 'status' => 'active' ) );
					$this->redirect_with_notice( 'activated' );
					break;
				case 'send_now':
					$this->handle_send_now( $id );
					break;
			}
		}
	}

	private function handle_save() {
		$data = array(
			'digest_name'  => sanitize_text_field( wp_unslash( $_POST['digest_name'] ?? '' ) ),
			'frequency'    => sanitize_text_field( wp_unslash( $_POST['frequency'] ?? 'weekly' ) ),
			'day_of_week'  => (int) ( $_POST['day_of_week'] ?? 1 ),
			'day_of_month' => (int) ( $_POST['day_of_month'] ?? 1 ),
			'send_hour'    => (int) ( $_POST['send_hour'] ?? 9 ),
			'sections'     => array_map( 'sanitize_text_field', (array) ( $_POST['sections'] ?? array() ) ),
			'recipients'   => array_filter( array_map( 'sanitize_email', explode( ',', wp_unslash( $_POST['recipients'] ?? '' ) ) ) ),
		);

		$id = (int) ( $_POST['digest_id'] ?? 0 );

		if ( $id > 0 ) {
			Dataviz_AI_Email_Digests::update( $id, $data );
			$this->redirect_with_notice( 'updated' );
		} else {
			$data['user_id'] = get_current_user_id();
			Dataviz_AI_Email_Digests::insert( $data );
			$this->redirect_with_notice( 'created' );
		}
	}

	private function handle_send_now( $id ) {
		$digest = Dataviz_AI_Email_Digests::get( $id );
		if ( ! $digest ) {
			$this->redirect_with_notice( 'not_found' );
			return;
		}

		$data = $this->generator->build( $digest );
		$html = Dataviz_AI_Digest_Email_Template::render( $data );

		$recipients = is_array( $digest->recipients ) ? $digest->recipients : array();
		$user = get_user_by( 'id', $digest->user_id );
		if ( $user && ! in_array( $user->user_email, $recipients, true ) ) {
			$recipients[] = $user->user_email;
		}
		$recipients = array_filter( array_unique( $recipients ), 'is_email' );

		if ( empty( $recipients ) ) {
			$this->redirect_with_notice( 'no_recipients' );
			return;
		}

		$subject = sprintf(
			'[%s] %s',
			get_bloginfo( 'name' ),
			$digest->digest_name
		);
		$headers = Dataviz_AI_Digest_Mailer::default_headers();

		$sent_count = 0;
		$errors     = array();
		foreach ( $recipients as $email ) {
			$result = Dataviz_AI_Digest_Mailer::send_html( $email, $subject, $html, $headers );
			if ( true === $result ) {
				$sent_count++;
			} else {
				$msg = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown error', 'dataviz-ai-woocommerce' );
				$errors[] = $email . ': ' . $msg;
			}
		}

		$uid = get_current_user_id();
		// Only advance schedule if at least one message was accepted by wp_mail().
		if ( $sent_count > 0 ) {
			Dataviz_AI_Email_Digests::mark_sent( $id );
		}

		$total = count( $recipients );
		if ( $sent_count === $total ) {
			$this->redirect_with_notice( 'sent' );
		} elseif ( $sent_count > 0 ) {
			set_transient( 'dataviz_digest_mail_detail_' . $uid, implode( "\n", $errors ), 120 );
			$this->redirect_with_notice( 'send_partial' );
		} else {
			set_transient( 'dataviz_digest_mail_detail_' . $uid, implode( "\n", $errors ), 120 );
			$this->redirect_with_notice( 'send_failed' );
		}
	}

	private function redirect_with_notice( $notice ) {
		wp_safe_redirect( add_query_arg(
			array(
				'page'   => 'dataviz-ai-digests',
				'notice' => $notice,
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// ------------------------------------------------------------------
	// List view
	// ------------------------------------------------------------------

	private function render_list() {
		$digests = Dataviz_AI_Email_Digests::get_all();
		$days    = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
		?>
		<div class="wrap dataviz-digest-wrap">
			<?php $this->render_email_delivery_help(); ?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Email Digests', 'dataviz-ai-woocommerce' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Digest', 'dataviz-ai-woocommerce' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<?php if ( empty( $digests ) ) : ?>
				<div class="dataviz-digest-empty">
					<div class="dataviz-digest-empty__icon">&#128236;</div>
					<h2><?php esc_html_e( 'No digests configured yet', 'dataviz-ai-woocommerce' ); ?></h2>
					<p><?php esc_html_e( 'Schedule automated email reports to stay on top of your store performance without logging in.', 'dataviz-ai-woocommerce' ); ?></p>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary button-hero"><?php esc_html_e( 'Create Your First Digest', 'dataviz-ai-woocommerce' ); ?></a>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped dataviz-digest-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'dataviz-ai-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Frequency', 'dataviz-ai-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Schedule', 'dataviz-ai-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Sections', 'dataviz-ai-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'dataviz-ai-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Last Sent', 'dataviz-ai-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Next Run', 'dataviz-ai-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $digests as $d ) : ?>
					<?php
					$edit_url    = add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => 'edit', 'digest_id' => $d->id ), admin_url( 'admin.php' ) );
					$preview_url = add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => 'preview', 'digest_id' => $d->id ), admin_url( 'admin.php' ) );
					$delete_url  = wp_nonce_url( add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => 'delete', 'digest_id' => $d->id ), admin_url( 'admin.php' ) ), 'dataviz_digest_delete_' . $d->id );
					$toggle_action = $d->status === 'active' ? 'pause' : 'activate';
					$toggle_label  = $d->status === 'active' ? __( 'Pause', 'dataviz-ai-woocommerce' ) : __( 'Activate', 'dataviz-ai-woocommerce' );
					$toggle_url    = wp_nonce_url( add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => $toggle_action, 'digest_id' => $d->id ), admin_url( 'admin.php' ) ), 'dataviz_digest_' . $toggle_action . '_' . $d->id );
					$send_now_url  = wp_nonce_url( add_query_arg( array( 'page' => 'dataviz-ai-digests', 'digest_action' => 'send_now', 'digest_id' => $d->id ), admin_url( 'admin.php' ) ), 'dataviz_digest_send_now_' . $d->id );

					$schedule_label = '';
					if ( $d->frequency === 'daily' ) {
						$schedule_label = sprintf( __( 'Daily at %s', 'dataviz-ai-woocommerce' ), sprintf( '%02d:00', $d->send_hour ) );
					} elseif ( $d->frequency === 'weekly' ) {
						$schedule_label = sprintf( __( '%s at %s', 'dataviz-ai-woocommerce' ), $days[ $d->day_of_week % 7 ], sprintf( '%02d:00', $d->send_hour ) );
					} else {
						$schedule_label = sprintf( __( 'Day %d at %s', 'dataviz-ai-woocommerce' ), $d->day_of_month, sprintf( '%02d:00', $d->send_hour ) );
					}

					$sections_list = is_array( $d->sections ) ? $d->sections : array();
					$all_sections  = Dataviz_AI_Email_Digests::available_sections();
					$section_names = array();
					foreach ( $sections_list as $s ) {
						if ( isset( $all_sections[ $s ] ) ) {
							$section_names[] = $all_sections[ $s ];
						}
					}
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $d->digest_name ); ?></a></strong>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'dataviz-ai-woocommerce' ); ?></a> | </span>
								<span><a href="<?php echo esc_url( $preview_url ); ?>"><?php esc_html_e( 'Preview', 'dataviz-ai-woocommerce' ); ?></a> | </span>
								<span><a href="<?php echo esc_url( $send_now_url ); ?>"><?php esc_html_e( 'Send Now', 'dataviz-ai-woocommerce' ); ?></a> | </span>
								<span><a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo esc_html( $toggle_label ); ?></a> | </span>
								<span class="delete"><a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this digest?', 'dataviz-ai-woocommerce' ); ?>');"><?php esc_html_e( 'Delete', 'dataviz-ai-woocommerce' ); ?></a></span>
							</div>
						</td>
						<td><span class="dataviz-digest-badge dataviz-digest-badge--<?php echo esc_attr( $d->frequency ); ?>"><?php echo esc_html( ucfirst( $d->frequency ) ); ?></span></td>
						<td><?php echo esc_html( $schedule_label ); ?></td>
						<td><span class="dataviz-digest-sections"><?php echo esc_html( implode( ', ', $section_names ) ); ?></span></td>
						<td>
							<?php if ( $d->status === 'active' ) : ?>
								<span class="dataviz-digest-status dataviz-digest-status--active"><?php esc_html_e( 'Active', 'dataviz-ai-woocommerce' ); ?></span>
							<?php else : ?>
								<span class="dataviz-digest-status dataviz-digest-status--paused"><?php esc_html_e( 'Paused', 'dataviz-ai-woocommerce' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $d->last_sent_at ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $d->last_sent_at ) ) ) : '&mdash;'; ?></td>
						<td><?php echo $d->next_run_at ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $d->next_run_at ) ) ) : '&mdash;'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Create / Edit form
	// ------------------------------------------------------------------

	private function render_form() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$id     = (int) ( $_GET['digest_id'] ?? 0 );
		$digest = $id > 0 ? Dataviz_AI_Email_Digests::get( $id ) : null;

		$name       = $digest ? $digest->digest_name : __( 'Weekly Sales Digest', 'dataviz-ai-woocommerce' );
		$frequency  = $digest ? $digest->frequency : 'weekly';
		$dow        = $digest ? (int) $digest->day_of_week : 1;
		$dom        = $digest ? (int) $digest->day_of_month : 1;
		$hour       = $digest ? (int) $digest->send_hour : 9;
		$recipients = $digest ? implode( ', ', (array) $digest->recipients ) : get_option( 'admin_email' );
		$sections   = $digest ? (array) $digest->sections : array_keys( Dataviz_AI_Email_Digests::available_sections() );

		$all_sections  = Dataviz_AI_Email_Digests::available_sections();
		$frequencies   = Dataviz_AI_Email_Digests::frequencies();
		$days_of_week  = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$is_edit       = $id > 0;
		?>
		<div class="wrap dataviz-digest-wrap">
			<h1><?php echo $is_edit ? esc_html__( 'Edit Digest', 'dataviz-ai-woocommerce' ) : esc_html__( 'New Digest', 'dataviz-ai-woocommerce' ); ?></h1>
			<hr class="wp-header-end">

			<form method="post" class="dataviz-digest-form">
				<?php wp_nonce_field( 'dataviz_digest_save', 'dataviz_digest_nonce' ); ?>
				<input type="hidden" name="digest_id" value="<?php echo esc_attr( $id ); ?>">

				<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><label for="digest_name"><?php esc_html_e( 'Digest Name', 'dataviz-ai-woocommerce' ); ?></label></th>
					<td><input type="text" id="digest_name" name="digest_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
				</tr>

				<tr>
					<th scope="row"><label for="frequency"><?php esc_html_e( 'Frequency', 'dataviz-ai-woocommerce' ); ?></label></th>
					<td>
						<select id="frequency" name="frequency">
							<?php foreach ( $frequencies as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $frequency, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="dataviz-digest-row--weekly" <?php echo $frequency !== 'weekly' ? 'style="display:none"' : ''; ?>>
					<th scope="row"><label for="day_of_week"><?php esc_html_e( 'Day of Week', 'dataviz-ai-woocommerce' ); ?></label></th>
					<td>
						<select id="day_of_week" name="day_of_week">
							<?php foreach ( $days_of_week as $i => $d ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $dow, $i ); ?>><?php echo esc_html( $d ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="dataviz-digest-row--monthly" <?php echo $frequency !== 'monthly' ? 'style="display:none"' : ''; ?>>
					<th scope="row"><label for="day_of_month"><?php esc_html_e( 'Day of Month', 'dataviz-ai-woocommerce' ); ?></label></th>
					<td>
						<select id="day_of_month" name="day_of_month">
							<?php for ( $i = 1; $i <= 28; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $dom, $i ); ?>><?php echo esc_html( $i ); ?></option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Capped at 28 to work for all months.', 'dataviz-ai-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="send_hour"><?php esc_html_e( 'Send Time', 'dataviz-ai-woocommerce' ); ?></label></th>
					<td>
						<select id="send_hour" name="send_hour">
							<?php for ( $h = 0; $h < 24; $h++ ) : ?>
								<option value="<?php echo esc_attr( $h ); ?>" <?php selected( $hour, $h ); ?>><?php echo esc_html( sprintf( '%02d:00', $h ) ); ?></option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Server timezone.', 'dataviz-ai-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="recipients"><?php esc_html_e( 'Recipients', 'dataviz-ai-woocommerce' ); ?></label></th>
					<td>
						<input type="text" id="recipients" name="recipients" value="<?php echo esc_attr( $recipients ); ?>" class="large-text">
						<p class="description"><?php esc_html_e( 'Comma-separated email addresses. The creating user is always included.', 'dataviz-ai-woocommerce' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Report Sections', 'dataviz-ai-woocommerce' ); ?></th>
					<td>
						<fieldset>
						<?php foreach ( $all_sections as $slug => $label ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="sections[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $sections, true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>

				</table>

				<?php submit_button( $is_edit ? __( 'Update Digest', 'dataviz-ai-woocommerce' ) : __( 'Create Digest', 'dataviz-ai-woocommerce' ) ); ?>
			</form>

			<script>
			(function(){
				var freq = document.getElementById('frequency');
				if (!freq) return;
				freq.addEventListener('change', function(){
					document.querySelectorAll('.dataviz-digest-row--weekly').forEach(function(r){ r.style.display = freq.value === 'weekly' ? '' : 'none'; });
					document.querySelectorAll('.dataviz-digest-row--monthly').forEach(function(r){ r.style.display = freq.value === 'monthly' ? '' : 'none'; });
				});
			})();
			</script>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Preview view
	// ------------------------------------------------------------------

	private function render_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$id     = (int) ( $_GET['digest_id'] ?? 0 );
		$digest = $id > 0 ? Dataviz_AI_Email_Digests::get( $id ) : null;

		if ( ! $digest ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Digest not found.', 'dataviz-ai-woocommerce' ) . '</p></div></div>';
			return;
		}

		$data = $this->generator->build( $digest );
		$html = Dataviz_AI_Digest_Email_Template::render( $data );

		$back_url = add_query_arg( array( 'page' => 'dataviz-ai-digests' ), admin_url( 'admin.php' ) );
		?>
		<div class="wrap dataviz-digest-wrap">
			<h1>
				<?php esc_html_e( 'Email Preview', 'dataviz-ai-woocommerce' ); ?>
				<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Digests', 'dataviz-ai-woocommerce' ); ?></a>
			</h1>
			<hr class="wp-header-end">
			<div class="dataviz-digest-preview">
				<iframe srcdoc="<?php echo esc_attr( $html ); ?>" style="width:100%;height:800px;border:1px solid #dcdcde;border-radius:6px;background:#fff;"></iframe>
			</div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Notices
	// ------------------------------------------------------------------

	private function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$notice = isset( $_GET['notice'] ) ? sanitize_text_field( wp_unslash( $_GET['notice'] ) ) : '';
		if ( ! $notice ) {
			return;
		}

		$messages = array(
			'created'       => __( 'Digest created successfully.', 'dataviz-ai-woocommerce' ),
			'updated'       => __( 'Digest updated successfully.', 'dataviz-ai-woocommerce' ),
			'deleted'       => __( 'Digest deleted.', 'dataviz-ai-woocommerce' ),
			'paused'        => __( 'Digest paused.', 'dataviz-ai-woocommerce' ),
			'activated'     => __( 'Digest activated.', 'dataviz-ai-woocommerce' ),
			'sent'          => __( 'Digest sent successfully.', 'dataviz-ai-woocommerce' ),
			'send_partial'  => __( 'Digest partially sent — some addresses failed (see details below).', 'dataviz-ai-woocommerce' ),
			'send_failed'   => __( 'Email was not sent. Your server may not be configured to send mail (common on local/Docker).', 'dataviz-ai-woocommerce' ),
			'no_recipients' => __( 'No valid recipients found.', 'dataviz-ai-woocommerce' ),
			'not_found'     => __( 'Digest not found.', 'dataviz-ai-woocommerce' ),
		);

		$type = in_array( $notice, array( 'no_recipients', 'not_found', 'send_failed' ), true ) ? 'error' : 'success';
		if ( in_array( $notice, array( 'send_partial' ), true ) ) {
			$type = 'warning';
		}

		$msg = $messages[ $notice ] ?? '';

		if ( $msg ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $msg ) );
		}

		if ( in_array( $notice, array( 'send_failed', 'send_partial' ), true ) ) {
			$detail = get_transient( 'dataviz_digest_mail_detail_' . get_current_user_id() );
			if ( $detail ) {
				delete_transient( 'dataviz_digest_mail_detail_' . get_current_user_id() );
				printf(
					'<div class="notice notice-%s"><p><strong>%s</strong></p><pre class="dataviz-digest-mail-detail">%s</pre></div>',
					esc_attr( 'send_partial' === $notice ? 'warning' : 'error' ),
					esc_html__( 'Details:', 'dataviz-ai-woocommerce' ),
					esc_html( $detail )
				);
			}
		}
	}

	/**
	 * Explain why mail might not arrive (local dev, SMTP).
	 */
	private function render_email_delivery_help() {
		?>
		<div class="notice notice-info inline" style="margin:12px 0 8px;padding:12px 14px;">
			<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Not receiving digest emails?', 'dataviz-ai-woocommerce' ); ?></strong></p>
			<ul style="margin:0 0 0 1.2em;list-style:disc;">
				<li><?php esc_html_e( 'WordPress uses PHP mail() by default. Local Docker/Vagrant and many hosts do not deliver it — install an SMTP plugin (e.g. WP Mail SMTP) and use Mailtrap, Gmail SMTP, or your host’s SMTP.', 'dataviz-ai-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'After clicking “Send Now”, check for an error notice above — we now show the real failure reason when available.', 'dataviz-ai-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Scheduled sends rely on WP-Cron (triggered by site visits). Low-traffic sites may need a system cron hitting wp-cron.php.', 'dataviz-ai-woocommerce' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
