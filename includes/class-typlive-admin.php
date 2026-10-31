<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TYPLive_Admin {
	/** @var string */
	private $option_key;

	public function __construct( $option_key ) {
		$this->option_key = $option_key;
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_typlive_check_updates', array( $this, 'check_for_updates' ) );
		add_action( 'admin_post_typlive_check_schedule', array( $this, 'check_schedule_now' ) );
		add_action( 'admin_post_typlive_clear_schedule_alerts', array( $this, 'clear_schedule_alerts' ) );
		add_action( 'admin_notices', array( $this, 'schedule_alert_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TYPLIVE_SCOREBOARDS_FILE ), array( $this, 'settings_link' ) );
	}

	public function menu() {
		add_options_page( 'TYP Live Scoreboards', 'TYP Live Scoreboards', 'manage_options', 'typlive-scoreboards', array( $this, 'page' ) );
	}

	public function register_settings() {
		register_setting(
			'typlive_scoreboards_group',
			$this->option_key,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => TYPLive_Scoreboards::default_settings(),
			)
		);
	}

	public function sanitize( $input ) {
		$defaults = TYPLive_Scoreboards::default_settings();
		$current  = TYPLive_Scoreboards::settings();
		$input    = is_array( $input ) ? $input : array();

		$event_id    = isset( $input['selected_event_id'] ) ? absint( $input['selected_event_id'] ) : 0;
		$event_name  = isset( $input['selected_event_name'] ) ? sanitize_text_field( $input['selected_event_name'] ) : '';
		$event_venue = isset( $input['selected_event_venue'] ) ? sanitize_text_field( $input['selected_event_venue'] ) : '';
		$clean       = array(
			'mode'                 => isset( $input['mode'] ) && 'demo' === $input['mode'] ? 'demo' : 'live',
			'source_url'           => $defaults['source_url'],
			'event_name'           => $event_name ? $event_name : $defaults['event_name'],
			'selected_event_id'    => $event_id,
			'selected_event_name'  => $event_name,
			'selected_event_venue' => $event_venue,
			'accent'               => isset( $input['accent'] ) && sanitize_hex_color( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : $defaults['accent'],
			'refresh_seconds'      => isset( $input['refresh_seconds'] ) ? max( 3, min( 60, absint( $input['refresh_seconds'] ) ) ) : 5,
			'boards'               => array(),
		);

		$submitted = isset( $input['players'] ) && is_array( $input['players'] ) ? $input['players'] : array();
		foreach ( $submitted as $player_id => $player ) {
			if ( ! is_array( $player ) || empty( $player['selected'] ) ) {
				continue;
			}

			$player_id = absint( $player_id );
			$first     = isset( $player['first'] ) ? sanitize_text_field( $player['first'] ) : '';
			$last      = isset( $player['last'] ) ? sanitize_text_field( $player['last'] ) : '';
			$country   = isset( $player['country'] ) ? sanitize_text_field( $player['country'] ) : '';
			if ( ! $player_id || '' === trim( $first . $last ) ) {
				continue;
			}

			$display = trim( $first . ' ' . $this->title_case( $last ) );
			$slug    = sanitize_title( $first );
			if ( '' === $slug || isset( $clean['boards'][ $slug ] ) ) {
				$slug = sanitize_title( $display );
			}
			if ( isset( $clean['boards'][ $slug ] ) ) {
				$slug .= '-' . $player_id;
			}

			$clean['boards'][ $slug ] = array(
				'slug'           => $slug,
				'player_id'      => $player_id,
				'player_name'    => $display,
				'country'        => $country,
				'event_id'       => $event_id,
				'event_name'     => $event_name,
				'event_venue'    => $event_venue,
				'opponent_name'  => 'Opponent TBC',
				'player_score'   => 0,
				'opponent_score' => 0,
				'race_to'        => 7,
				'table'          => 'Table TBC',
			);
		}

		$clean['boards'] = array_merge( $clean['boards'], TYPLive_Scoreboards::tracked_boards() );

		if ( ! $event_id ) {
			add_settings_error( $this->option_key, 'typlive_no_event', 'Choose a tournament before saving.', 'error' );
			return $current;
		}

		if ( empty( $clean['boards'] ) ) {
			add_settings_error( $this->option_key, 'typlive_no_players', 'Select at least one player. Your previous scoreboard links were kept.', 'error' );
			return $current;
		}

		flush_rewrite_rules( false );
		return $clean;
	}

	public function settings_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=typlive-scoreboards' ) ) . '">Settings</a>' );
		return $links;
	}

	public function check_for_updates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to check plugin updates.', 'typlive-scoreboards' ) );
		}

		check_admin_referer( 'typlive_check_updates' );
		delete_site_transient( TYPLive_Updater::CACHE_KEY );
		delete_site_transient( 'update_plugins' );

		require_once ABSPATH . 'wp-admin/includes/update.php';
		wp_update_plugins();

		$release = get_site_transient( TYPLive_Updater::CACHE_KEY );
		$status  = 'error';
		$version = '';
		if ( is_array( $release ) && ! empty( $release['version'] ) ) {
			$version = sanitize_text_field( $release['version'] );
			$status  = version_compare( $version, TYPLIVE_SCOREBOARDS_VERSION, '>' ) ? 'available' : 'current';
		}

		$redirect = add_query_arg(
			array(
				'page'                  => 'typlive-scoreboards',
				'typlive_update_check' => $status,
				'typlive_version'      => $version,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function check_schedule_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to check the schedule.', 'typlive-scoreboards' ) );
		}

		check_admin_referer( 'typlive_check_schedule' );
		$result = TYPLive_Scoreboards::instance()->monitor_schedule_changes( true );
		$status = empty( $result['ok'] ) ? 'error' : ( ! empty( $result['changes'] ) ? 'changed' : 'current' );
		$redirect = add_query_arg(
			array(
				'page'                    => 'typlive-scoreboards',
				'typlive_schedule_check' => $status,
				'typlive_changes'        => isset( $result['changes'] ) ? absint( $result['changes'] ) : 0,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function clear_schedule_alerts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear schedule alerts.', 'typlive-scoreboards' ) );
		}

		check_admin_referer( 'typlive_clear_schedule_alerts' );
		delete_option( TYPLive_Scoreboards::ALERTS_OPTION );
		$redirect = add_query_arg(
			array(
				'page'                     => 'typlive-scoreboards',
				'typlive_alerts_cleared' => 1,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public function schedule_alert_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$alerts = get_option( TYPLive_Scoreboards::ALERTS_OPTION, array() );
		if ( ! is_array( $alerts ) || empty( $alerts ) ) {
			return;
		}
		$url = admin_url( 'options-general.php?page=typlive-scoreboards#typlive-schedule-alerts' );
		echo '<div class="notice notice-warning"><p><strong>Spots &amp; Stripes schedule change:</strong> ' . esc_html( count( $alerts ) ) . ' opponent, time or table update(s) need reviewing. <a href="' . esc_url( $url ) . '">View schedule alerts</a>.</p></div>';
	}

	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings    = TYPLive_Scoreboards::settings();
		$source_status = TYPLive_DigitalPool::source_status();
		$schedule_alerts = get_option( TYPLive_Scoreboards::ALERTS_OPTION, array() );
		$schedule_alerts = is_array( $schedule_alerts ) ? $schedule_alerts : array();
		$api         = new TYPLive_Agoneus();
		$tournaments = $api->tournaments();
		$event_id    = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : absint( isset( $settings['selected_event_id'] ) ? $settings['selected_event_id'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $event_id && ! is_wp_error( $tournaments ) && ! empty( $tournaments ) ) {
			$event_id = absint( $tournaments[0]['id'] );
		}

		$event   = $event_id ? $api->tournament( $event_id ) : null;
		$players = $event_id ? $api->players( $event_id ) : array();
		$selected_ids = array();
		foreach ( $settings['boards'] as $board ) {
			if ( absint( isset( $board['event_id'] ) ? $board['event_id'] : 0 ) === $event_id && ! empty( $board['player_id'] ) ) {
				$selected_ids[] = absint( $board['player_id'] );
			}
		}
		?>
		<div class="wrap typlive-admin">
			<div class="typlive-admin-heading">
				<div><h1>TYP Live Scoreboards</h1><p>Pick the tournament, select the players, then copy their generated Streamlabs URLs.</p></div>
				<span class="typlive-provider"><span></span> All Scores Live + DigitalPool</span>
			</div>

			<?php
			$update_status  = isset( $_GET['typlive_update_check'] ) ? sanitize_key( wp_unslash( $_GET['typlive_update_check'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$update_version = isset( $_GET['typlive_version'] ) ? sanitize_text_field( wp_unslash( $_GET['typlive_version'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$schedule_check = isset( $_GET['typlive_schedule_check'] ) ? sanitize_key( wp_unslash( $_GET['typlive_schedule_check'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$schedule_changes = isset( $_GET['typlive_changes'] ) ? absint( $_GET['typlive_changes'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'current' === $update_status ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>Update check complete.</strong> Version ' . esc_html( TYPLIVE_SCOREBOARDS_VERSION ) . ' is current.</p></div>';
			} elseif ( 'available' === $update_status ) {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>Version ' . esc_html( $update_version ) . ' is available.</strong> WordPress automatic updates will install it, or you can update it now from <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugins</a>.</p></div>';
			} elseif ( 'error' === $update_status ) {
				echo '<div class="notice notice-error is-dismissible"><p><strong>The update check could not reach GitHub.</strong> Please try again in a moment.</p></div>';
			}
			if ( 'current' === $schedule_check ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>DigitalPool check complete.</strong> No match-time, opponent or table changes were found.</p></div>';
			} elseif ( 'changed' === $schedule_check ) {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>DigitalPool check complete.</strong> ' . esc_html( $schedule_changes ) . ' schedule change(s) were found.</p></div>';
			} elseif ( 'error' === $schedule_check ) {
				echo '<div class="notice notice-error is-dismissible"><p><strong>DigitalPool could not be reached.</strong> The previous schedule and alerts have been kept.</p></div>';
			}
			if ( ! empty( $_GET['typlive_alerts_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<div class="notice notice-success is-dismissible"><p>Schedule alerts cleared.</p></div>';
			}
			?>

			<section class="typlive-update-card">
				<div><h2>Plugin updates</h2><p>Installed version <?php echo esc_html( TYPLIVE_SCOREBOARDS_VERSION ); ?>. Automatic updates are enabled.</p></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="typlive_check_updates">
					<?php wp_nonce_field( 'typlive_check_updates' ); ?>
					<?php submit_button( 'Check for updates now', 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<?php
			$last_success = ! empty( $source_status['last_success_at'] ) && strtotime( $source_status['last_success_at'] )
				? wp_date( 'j M Y, H:i', strtotime( $source_status['last_success_at'] ), new DateTimeZone( 'Europe/London' ) ) . ' UK'
				: 'No successful check recorded yet';
			?>
			<section class="typlive-source-card<?php echo empty( $source_status['ok'] ) ? ' has-warning' : ''; ?>">
				<div class="typlive-source-copy"><span class="typlive-source-dot"></span><div><h2>DigitalPool schedule monitor</h2><p>Last successful update: <?php echo esc_html( $last_success ); ?>. Automatic checks run every five minutes through WordPress.</p><?php if ( ! empty( $source_status['last_error'] ) ) : ?><small>Latest error: <?php echo esc_html( $source_status['last_error'] ); ?></small><?php endif; ?></div></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="typlive_check_schedule">
					<?php wp_nonce_field( 'typlive_check_schedule' ); ?>
					<?php submit_button( 'Check DigitalPool now', 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<?php if ( ! empty( $schedule_alerts ) ) : ?>
				<section class="typlive-alert-card" id="typlive-schedule-alerts">
					<div class="typlive-alert-heading"><div><h2>Schedule change alerts</h2><p>Table, opponent and match-time changes detected by the monitor.</p></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="typlive_clear_schedule_alerts"><?php wp_nonce_field( 'typlive_clear_schedule_alerts' ); ?><?php submit_button( 'Clear alerts', 'secondary', 'submit', false ); ?></form></div>
					<div class="typlive-alert-list">
						<?php foreach ( array_slice( $schedule_alerts, 0, 12 ) as $alert ) : ?>
							<div><strong><?php echo esc_html( $alert['player'] . ' — ' . $alert['field'] ); ?></strong><span><del><?php echo esc_html( $alert['old'] ); ?></del> <b>→</b> <?php echo esc_html( $alert['new'] ); ?></span><small><?php echo esc_html( wp_date( 'j M, H:i', strtotime( $alert['detected_at'] ), new DateTimeZone( 'Europe/London' ) ) . ' UK' ); ?></small></div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="typlive-step">
				<div class="typlive-step-number">1</div>
				<div class="typlive-step-body">
					<h2>Choose a tournament</h2>
					<p class="description">Ultimate Pool USA tournaments available for Spots &amp; Stripes on Tour coverage.</p>
					<?php if ( is_wp_error( $tournaments ) ) : ?>
						<div class="notice notice-error inline"><p><?php echo esc_html( $tournaments->get_error_message() ); ?></p></div>
					<?php else : ?>
						<form method="get" class="typlive-event-form">
							<input type="hidden" name="page" value="typlive-scoreboards">
							<select name="event" id="typlive-event" onchange="this.form.submit()">
								<?php foreach ( $tournaments as $tournament ) : ?>
									<option value="<?php echo esc_attr( $tournament['id'] ); ?>" <?php selected( $event_id, $tournament['id'] ); ?>><?php echo esc_html( $this->tournament_label( $tournament ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<noscript><?php submit_button( 'Load players', 'secondary', '', false ); ?></noscript>
						</form>
					<?php endif; ?>
				</div>
			</section>

			<form method="post" action="options.php" id="typlive-player-form">
				<?php settings_fields( 'typlive_scoreboards_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[selected_event_id]" value="<?php echo esc_attr( $event_id ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[selected_event_name]" value="<?php echo esc_attr( is_array( $event ) ? $event['name'] : '' ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[selected_event_venue]" value="<?php echo esc_attr( is_array( $event ) ? $event['venue'] : '' ); ?>">

				<section class="typlive-step">
					<div class="typlive-step-number">2</div>
					<div class="typlive-step-body">
						<h2>Select players</h2>
						<?php if ( is_wp_error( $players ) ) : ?>
							<div class="notice notice-error inline"><p><?php echo esc_html( $players->get_error_message() ); ?></p></div>
						<?php elseif ( empty( $players ) ) : ?>
							<div class="notice notice-warning inline"><p>No player entries are published for this tournament yet. Reload this page after the event list is released.</p></div>
						<?php else : ?>
							<div class="typlive-player-tools">
								<input type="search" id="typlive-player-search" placeholder="Search <?php echo esc_attr( count( $players ) ); ?> players…">
								<button type="button" class="button" data-select-visible>Select visible</button>
								<button type="button" class="button-link" data-clear-players>Clear</button>
							</div>
							<div class="typlive-player-list" id="typlive-player-list">
								<?php foreach ( $players as $player ) : ?>
									<?php $display = trim( $player['first'] . ' ' . $this->title_case( $player['last'] ) ); ?>
									<label class="typlive-player-option" data-player-name="<?php echo esc_attr( strtolower( $display ) ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[players][<?php echo esc_attr( $player['id'] ); ?>][selected]" value="1" <?php checked( in_array( absint( $player['id'] ), $selected_ids, true ) ); ?>>
										<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[players][<?php echo esc_attr( $player['id'] ); ?>][first]" value="<?php echo esc_attr( $player['first'] ); ?>">
										<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[players][<?php echo esc_attr( $player['id'] ); ?>][last]" value="<?php echo esc_attr( $player['last'] ); ?>">
										<input type="hidden" name="<?php echo esc_attr( $this->option_key ); ?>[players][<?php echo esc_attr( $player['id'] ); ?>][country]" value="<?php echo esc_attr( $player['country'] ); ?>">
										<span class="typlive-check"></span>
										<span><strong><?php echo esc_html( $display ); ?></strong><small><?php echo esc_html( $player['country'] ); ?></small></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</section>

				<section class="typlive-step">
					<div class="typlive-step-number">3</div>
					<div class="typlive-step-body">
						<h2>Overlay settings</h2>
						<div class="typlive-settings-row">
							<label>Data mode<select name="<?php echo esc_attr( $this->option_key ); ?>[mode]"><option value="live" <?php selected( $settings['mode'], 'live' ); ?>>Live scores</option><option value="demo" <?php selected( $settings['mode'], 'demo' ); ?>>Design preview</option></select></label>
							<label>Accent colour <input type="color" name="<?php echo esc_attr( $this->option_key ); ?>[accent]" value="<?php echo esc_attr( $settings['accent'] ); ?>"></label>
							<label>Refresh every <span><input type="number" min="3" max="60" name="<?php echo esc_attr( $this->option_key ); ?>[refresh_seconds]" value="<?php echo esc_attr( $settings['refresh_seconds'] ); ?>"> seconds</span></label>
						</div>
						<?php submit_button( 'Save players & generate URLs', 'primary large', 'submit', false ); ?>
					</div>
				</section>
			</form>

			<?php if ( ! empty( $settings['boards'] ) ) : ?>
				<section class="typlive-links">
					<h2>Spots &amp; Stripes on Tour schedule</h2>
					<p>One public page combines the published schedules for all eight tracked players.</p>
					<?php $schedule_url = home_url( '/upusa-schedule/' ); ?>
					<div class="typlive-schedule-admin-link">
						<code><?php echo esc_html( $schedule_url ); ?></code>
						<a class="button button-primary" href="<?php echo esc_url( $schedule_url ); ?>" target="_blank" rel="noopener">Preview schedule</a>
						<button type="button" class="button" data-copy-url="<?php echo esc_attr( $schedule_url ); ?>">Copy schedule URL</button>
					</div>
					<h2 class="typlive-scoreboard-heading">Scoreboard URLs</h2>
					<p>These links remain transparent scoreboard-only pages for Streamlabs.</p>
					<div class="typlive-url-list">
						<?php foreach ( $settings['boards'] as $board ) : ?>
							<?php $url = home_url( '/scoreboard-' . $board['slug'] . '/' ); ?>
							<div class="typlive-url-row">
								<strong><?php echo esc_html( $board['player_name'] ); ?></strong>
								<code><?php echo esc_html( $url ); ?></code>
								<a class="button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Preview</a>
								<button type="button" class="button" data-copy-url="<?php echo esc_attr( $url ); ?>">Copy URL</button>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
		<?php $this->page_assets( $settings['accent'] ); ?>
		<?php
	}

	private function tournament_label( $event ) {
		$date = ! empty( $event['start'] ) ? date_i18n( 'j M Y', strtotime( $event['start'] ) ) : '';
		return $event['name'] . ( $date ? ' — ' . $date : '' );
	}

	private function title_case( $value ) {
		return ucwords( strtolower( trim( (string) $value ) ) );
	}

	private function page_assets( $accent ) {
		?>
		<style>
			.typlive-admin { max-width:1120px; }.typlive-admin-heading{display:flex;align-items:center;justify-content:space-between;margin:22px 0 20px}.typlive-admin-heading h1{margin:0 0 5px}.typlive-admin-heading p{margin:0;color:#646970}.typlive-provider{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid #dcdcde;border-radius:999px;font-weight:600}.typlive-provider span{width:8px;height:8px;background:#46b450;border-radius:50%;box-shadow:0 0 0 3px rgba(70,180,80,.13)}.typlive-update-card{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:0 0 14px;padding:16px 20px;background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid <?php echo esc_attr( $accent ); ?>;border-radius:6px}.typlive-update-card h2{margin:0 0 4px;font-size:15px}.typlive-update-card p{margin:0;color:#646970}.typlive-update-card form{flex:0 0 auto}
			.typlive-source-card{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:0 0 14px;padding:16px 20px;background:#fff;border:1px solid #dcdcde;border-left:4px solid #46b450;border-radius:6px}.typlive-source-card.has-warning{border-left-color:#dba617}.typlive-source-copy{display:flex;align-items:flex-start;gap:12px}.typlive-source-dot{flex:0 0 auto;width:10px;height:10px;margin-top:5px;background:#46b450;border-radius:50%;box-shadow:0 0 0 4px rgba(70,180,80,.12)}.has-warning .typlive-source-dot{background:#dba617;box-shadow:0 0 0 4px rgba(219,166,23,.13)}.typlive-source-card h2,.typlive-alert-card h2{margin:0 0 4px;font-size:15px}.typlive-source-card p,.typlive-alert-card p{margin:0;color:#646970}.typlive-source-card small{display:block;margin-top:5px;color:#b32d2e}.typlive-source-card form{flex:0 0 auto}.typlive-alert-card{margin:0 0 14px;padding:18px 20px;background:#fff;border:1px solid #dcdcde;border-left:4px solid #dba617;border-radius:6px}.typlive-alert-heading{display:flex;align-items:center;justify-content:space-between;gap:20px}.typlive-alert-list{display:grid;gap:7px;margin-top:14px}.typlive-alert-list>div{display:grid;grid-template-columns:220px minmax(0,1fr) auto;align-items:center;gap:12px;padding:10px 12px;background:#f6f7f7;border-radius:4px}.typlive-alert-list span{overflow-wrap:anywhere}.typlive-alert-list del{color:#8c8f94}.typlive-alert-list b{padding:0 5px;color:<?php echo esc_attr( $accent ); ?>}.typlive-alert-list small{color:#646970;white-space:nowrap}
			.typlive-step{display:grid;grid-template-columns:42px 1fr;gap:16px;margin:0 0 14px;padding:22px;background:#fff;border:1px solid #dcdcde;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.typlive-step-number{display:grid;place-items:center;width:36px;height:36px;background:<?php echo esc_attr( $accent ); ?>;color:#101214;border-radius:50%;font-size:16px;font-weight:800}.typlive-step h2{margin:3px 0 5px;font-size:18px}.typlive-step .description{margin:0 0 14px}.typlive-event-form select{width:min(720px,100%);min-height:42px}
			.typlive-player-tools{display:flex;align-items:center;gap:12px;margin:14px 0}.typlive-player-tools input{width:min(420px,100%);min-height:40px}.typlive-player-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));max-height:440px;overflow:auto;border:1px solid #dcdcde;border-radius:6px}.typlive-player-option{position:relative;display:flex;align-items:center;gap:11px;min-height:58px;padding:9px 12px;border-right:1px solid #eee;border-bottom:1px solid #eee;cursor:pointer}.typlive-player-option:hover{background:#f6f7f7}.typlive-player-option.is-hidden{display:none}.typlive-player-option input[type=checkbox]{position:absolute;opacity:0;pointer-events:none}.typlive-check{flex:0 0 auto;width:19px;height:19px;border:2px solid #8c8f94;border-radius:4px}.typlive-player-option input:checked~.typlive-check{background:<?php echo esc_attr( $accent ); ?>;border-color:#111;box-shadow:inset 0 0 0 3px #fff}.typlive-player-option strong{display:block;color:#1d2327}.typlive-player-option small{display:block;margin-top:2px;color:#8c8f94;font-size:10px;letter-spacing:.08em}
			.typlive-settings-row{display:flex;flex-wrap:wrap;align-items:end;gap:22px;margin:14px 0 20px}.typlive-settings-row label{display:grid;gap:6px;font-weight:600}.typlive-settings-row input[type=number]{width:80px}.typlive-links{margin-top:22px;padding:22px;background:#111417;color:#fff;border-top:4px solid <?php echo esc_attr( $accent ); ?>;border-radius:7px}.typlive-links h2{margin-top:0;color:#fff}.typlive-links h2.typlive-scoreboard-heading{margin-top:26px}.typlive-links p{color:#b9c0c5}.typlive-schedule-admin-link{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:10px;padding:12px;background:#1d2226;border-radius:5px}.typlive-schedule-admin-link code{overflow:hidden;color:<?php echo esc_attr( $accent ); ?>;text-overflow:ellipsis;white-space:nowrap}.typlive-url-list{display:grid;gap:8px}.typlive-url-row{display:grid;grid-template-columns:160px minmax(0,1fr) auto auto;align-items:center;gap:10px;padding:10px;background:#1d2226;border-radius:5px}.typlive-url-row code{overflow:hidden;color:<?php echo esc_attr( $accent ); ?>;text-overflow:ellipsis;white-space:nowrap}
			@media(max-width:900px){.typlive-player-list{grid-template-columns:repeat(2,minmax(0,1fr))}.typlive-alert-list>div{grid-template-columns:170px minmax(0,1fr)}.typlive-alert-list small{grid-column:1/-1}.typlive-schedule-admin-link,.typlive-url-row{grid-template-columns:1fr auto auto}.typlive-schedule-admin-link code,.typlive-url-row code{grid-column:1/-1;grid-row:2}}@media(max-width:600px){.typlive-admin-heading{align-items:flex-start;gap:14px}.typlive-provider{display:none}.typlive-update-card,.typlive-source-card,.typlive-alert-heading{align-items:flex-start;flex-direction:column}.typlive-alert-list>div{grid-template-columns:1fr}.typlive-step{grid-template-columns:1fr;padding:16px}.typlive-player-tools{flex-wrap:wrap}.typlive-player-list{grid-template-columns:1fr}.typlive-schedule-admin-link,.typlive-url-row{grid-template-columns:1fr 1fr}.typlive-schedule-admin-link code,.typlive-url-row strong,.typlive-url-row code{grid-column:1/-1}.typlive-schedule-admin-link .button,.typlive-url-row .button{justify-content:center;text-align:center}}
		</style>
		<script>
		(() => { const search=document.querySelector('#typlive-player-search'),list=document.querySelector('#typlive-player-list'); if(search&&list){search.addEventListener('input',()=>{const q=search.value.trim().toLowerCase();list.querySelectorAll('[data-player-name]').forEach(row=>row.classList.toggle('is-hidden',q&&!row.dataset.playerName.includes(q)))});document.querySelector('[data-select-visible]')?.addEventListener('click',()=>list.querySelectorAll('.typlive-player-option:not(.is-hidden) input[type=checkbox]').forEach(box=>box.checked=true));document.querySelector('[data-clear-players]')?.addEventListener('click',()=>list.querySelectorAll('input[type=checkbox]').forEach(box=>box.checked=false))} document.querySelectorAll('[data-copy-url]').forEach(button=>button.addEventListener('click',async()=>{await navigator.clipboard.writeText(button.dataset.copyUrl);const old=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=old,1200)})); })();
		</script>
		<?php
	}
}

