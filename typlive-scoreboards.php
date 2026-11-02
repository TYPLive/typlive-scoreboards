<?php
/**
 * Plugin Name: TYP Live Scoreboards
 * Plugin URI: https://github.com/TYPLive/typlive-scoreboards
 * Description: Streamlabs-ready live pool scoreboards with stable player URLs.
 * Version: 0.5.6
 * Author: TYP Live
 * Author URI: https://typlive.com/
 * Update URI: https://github.com/TYPLive/typlive-scoreboards
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: typlive-scoreboards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TYPLIVE_SCOREBOARDS_VERSION', '0.5.6' );
define( 'TYPLIVE_SCOREBOARDS_FILE', __FILE__ );
define( 'TYPLIVE_SCOREBOARDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'TYPLIVE_SCOREBOARDS_URL', plugin_dir_url( __FILE__ ) );

require_once TYPLIVE_SCOREBOARDS_DIR . 'includes/class-typlive-agoneus.php';
require_once TYPLIVE_SCOREBOARDS_DIR . 'includes/class-typlive-digitalpool.php';
require_once TYPLIVE_SCOREBOARDS_DIR . 'includes/class-typlive-feed.php';
require_once TYPLIVE_SCOREBOARDS_DIR . 'includes/class-typlive-admin.php';
require_once TYPLIVE_SCOREBOARDS_DIR . 'includes/class-typlive-updater.php';

final class TYPLive_Scoreboards {
	const OPTION_KEY = 'typlive_scoreboards_settings';
	const MONITOR_HOOK = 'typlive_schedule_monitor';
	const SNAPSHOT_OPTION = 'typlive_schedule_snapshot';
	const ALERTS_OPTION = 'typlive_schedule_alerts';
	const CALENDAR_STATE_OPTION = 'typlive_calendar_event_state';
	const LATEST_SCHEDULE_OPTION = 'typlive_latest_schedule_data';
	const MONITOR_RECURRENCE = 'typlive_one_minute';

	/** @var TYPLive_Scoreboards|null */
	private static $instance = null;

	/** @return TYPLive_Scoreboards */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		new TYPLive_Updater( TYPLIVE_SCOREBOARDS_FILE );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'ensure_schedule_monitor' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( self::MONITOR_HOOK, array( $this, 'monitor_schedule_changes' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_scoreboard_route' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_shortcode( 'typlive_scoreboard', array( $this, 'shortcode' ) );

		if ( is_admin() ) {
			new TYPLive_Admin( self::OPTION_KEY );
		}
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::default_settings() );
		}
		self::remove_legacy_notification_settings();

		self::instance()->register_routes();
		self::instance()->ensure_schedule_monitor();
		flush_rewrite_rules();
		update_option( 'typlive_scoreboards_rewrite_version', TYPLIVE_SCOREBOARDS_VERSION, false );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::MONITOR_HOOK );
		flush_rewrite_rules();
	}

	private static function remove_legacy_notification_settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( is_array( $settings ) ) {
			foreach ( array( 'onesignal_enabled', 'onesignal_app_id', 'onesignal_api_key', 'notification_reminder_minutes' ) as $key ) {
				unset( $settings[ $key ] );
			}
			update_option( self::OPTION_KEY, $settings, false );
		}
		delete_option( 'typlive_onesignal_status' );
		delete_option( 'typlive_match_reminders_sent' );
	}

	public function cron_schedules( $schedules ) {
		$schedules[ self::MONITOR_RECURRENCE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Every minute',
		);
		return $schedules;
	}

	public function ensure_schedule_monitor() {
		$event = wp_get_scheduled_event( self::MONITOR_HOOK );
		if ( ! $event || self::MONITOR_RECURRENCE !== $event->schedule ) {
			wp_clear_scheduled_hook( self::MONITOR_HOOK );
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::MONITOR_RECURRENCE, self::MONITOR_HOOK );
		}
	}

	public static function default_settings() {
		return array(
			'mode'            => 'live',
			'source_url'      => 'https://agoneus.com/upusa/livescoring/',
			'event_name'      => 'Spots & Stripes on Tour',
			'selected_event_id'    => 0,
			'selected_event_name'  => '',
			'selected_event_venue' => '',
			'accent'          => '#E32934',
			'refresh_seconds' => 5,
			'boards'          => self::tracked_boards(),
		);
	}

	public static function tracked_boards() {
		$players = array(
			'sam'     => 'Sam Jones',
			'nigel'   => 'Nigel Smith',
			'michael' => 'Michael Hope',
			'joe'     => 'Joe Prince',
			'ollie'   => 'Ollie Lowe',
			'ashley'  => 'Ashley Lord Withers',
			'sean'    => 'Sean Avery',
			'mary'    => 'Mary Talbot',
		);
		$boards  = array();

		foreach ( $players as $slug => $name ) {
			$boards[ $slug ] = array(
				'slug'           => $slug,
				'player_id'      => 0,
				'player_name'    => $name,
				'country'        => '',
				'event_id'       => 0,
				'event_name'     => 'Spots & Stripes on Tour',
				'event_venue'    => "Harrah's Cherokee Casino Resort",
				'opponent_name'  => 'Opponent TBC',
				'player_score'   => 0,
				'opponent_score' => 0,
				'race_to'        => 0,
				'table'          => 'Table TBC',
			);
		}

		return $boards;
	}

	public static function pair_boards() {
		return array(
			'pair-joe-prince-michael-hope' => self::pair_board(
				'pair-joe-prince-michael-hope',
				'Joe Prince & Michael Hope',
				array( 'Joe Prince', 'Michael Hope' )
			),
			'pair-mary-talbot-joe-prince' => self::pair_board(
				'pair-mary-talbot-joe-prince',
				'Mary Talbot & Joe Prince',
				array( 'Mary Talbot', 'Joe Prince' )
			),
		);
	}

	private static function pair_board( $slug, $name, $members = array() ) {
		if ( empty( $members ) ) {
			foreach ( self::tracked_boards() as $board ) {
				if ( false !== stripos( $name, $board['player_name'] ) ) {
					$members[] = $board['player_name'];
				}
			}
		}

		return array(
			'slug'           => sanitize_title( $slug ),
			'player_id'      => 0,
			'player_name'    => sanitize_text_field( $name ),
			'pair_members'   => array_values( array_unique( array_map( 'sanitize_text_field', $members ) ) ),
			'is_pair'        => true,
			'country'        => '',
			'event_id'       => 0,
			'event_name'     => 'Spots & Stripes on Tour',
			'event_venue'    => "Harrah's Cherokee Casino Resort",
			'opponent_name'  => 'Opponents TBC',
			'player_score'   => 0,
			'opponent_score' => 0,
			'race_to'        => 0,
			'table'          => 'Table TBC',
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( is_array( $saved ) && isset( $saved['accent'] ) && '#d8ff3e' === strtolower( (string) $saved['accent'] ) ) {
			$saved['accent'] = '#E32934';
		}
		if ( is_array( $saved ) && isset( $saved['event_name'] ) && 'ULTIMATE POOL USA' === strtoupper( trim( (string) $saved['event_name'] ) ) ) {
			$saved['event_name'] = 'Spots & Stripes on Tour';
		}
		$settings           = wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
		$saved_boards       = isset( $settings['boards'] ) && is_array( $settings['boards'] ) ? $settings['boards'] : array();
		$settings['boards'] = array_merge( $saved_boards, self::tracked_boards(), self::pair_boards() );
		return $settings;
	}

	public function register_routes() {
		add_rewrite_rule( '^scoreboard-([a-z0-9-]+)/?$', 'index.php?typlive_scoreboard=$matches[1]', 'top' );
		add_rewrite_rule( '^upusa-schedule/?$', 'index.php?typlive_upusa_schedule=1', 'top' );
		add_rewrite_rule( '^upusa-calendar\.ics$', 'index.php?typlive_upusa_calendar=all', 'top' );
		add_rewrite_rule( '^upusa-calendar-([a-z0-9-]+)\.ics$', 'index.php?typlive_upusa_calendar=$matches[1]', 'top' );
	}

	public function maybe_flush_rewrite_rules() {
		if ( TYPLIVE_SCOREBOARDS_VERSION !== get_option( 'typlive_scoreboards_rewrite_version' ) ) {
			self::remove_legacy_notification_settings();
			flush_rewrite_rules( false );
			update_option( 'typlive_scoreboards_rewrite_version', TYPLIVE_SCOREBOARDS_VERSION, false );
		}
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'typlive_scoreboard';
		$vars[] = 'typlive_upusa_schedule';
		$vars[] = 'typlive_upusa_calendar';
		return $vars;
	}

	public function register_rest_routes() {
		register_rest_route(
			'typlive/v1',
			'/score/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_score' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'slug' => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			'typlive/v1',
			'/schedule',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_schedule' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_score( WP_REST_Request $request ) {
		$slug  = sanitize_title( $request->get_param( 'slug' ) );
		$board = $this->get_board( $slug );

		if ( ! $board ) {
			return new WP_Error( 'typlive_unknown_scoreboard', 'Scoreboard not found.', array( 'status' => 404 ) );
		}

		$feed = new TYPLive_Feed( self::settings() );
		return rest_ensure_response( $feed->score_for( $board ) );
	}

	public function rest_schedule() {
		return rest_ensure_response( $this->schedule_data() );
	}

	public function render_scoreboard_route() {
		$calendar_slug = sanitize_title( (string) get_query_var( 'typlive_upusa_calendar' ) );
		if ( '' !== $calendar_slug ) {
			$this->output_calendar( $calendar_slug );
			exit;
		}

		if ( absint( get_query_var( 'typlive_upusa_schedule' ) ) ) {
			$this->output_schedule_page();
			exit;
		}

		$slug = sanitize_title( (string) get_query_var( 'typlive_scoreboard' ) );
		if ( '' === $slug ) {
			return;
		}

		$board = $this->get_board( $slug );
		if ( ! $board ) {
			status_header( 404 );
			nocache_headers();
			echo '<!doctype html><meta name="viewport" content="width=device-width"><title>Scoreboard not found</title><p>Scoreboard not found.</p>';
			exit;
		}

		$this->output_overlay( $slug, $board );
		exit;
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'slug' => 'sam' ), $atts, 'typlive_scoreboard' );
		$slug = sanitize_title( $atts['slug'] );
		$board = $this->get_board( $slug );

		if ( ! $board ) {
			return '';
		}

		wp_enqueue_style( 'typlive-scoreboard', TYPLIVE_SCOREBOARDS_URL . 'assets/scoreboard.css', array(), TYPLIVE_SCOREBOARDS_VERSION );
		wp_enqueue_script( 'typlive-scoreboard', TYPLIVE_SCOREBOARDS_URL . 'assets/scoreboard.js', array(), TYPLIVE_SCOREBOARDS_VERSION, true );

		$feed = new TYPLive_Feed( self::settings() );
		$data = $feed->score_for( $board );
		$config = $this->browser_config( $slug, $data );

		ob_start();
		include TYPLIVE_SCOREBOARDS_DIR . 'templates/scoreboard.php';
		return (string) ob_get_clean();
	}

	private function output_overlay( $slug, $board ) {
		$settings = self::settings();
		$feed     = new TYPLive_Feed( $settings );
		$data     = $feed->score_for( $board );
		$config   = $this->browser_config( $slug, $data );

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		include TYPLIVE_SCOREBOARDS_DIR . 'templates/overlay.php';
	}

	private function output_schedule_page() {
		$data   = $this->schedule_data();
		$config = array(
			'endpoint'       => esc_url_raw( rest_url( 'typlive/v1/schedule' ) ),
			'refreshSeconds' => 30,
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		include TYPLIVE_SCOREBOARDS_DIR . 'templates/schedule-page.php';
	}

	private function output_calendar( $slug ) {
		$data       = $this->calendar_data();
		$valid      = 'all' === $slug;
		$feed_name  = 'Spots & Stripes on Tour';
		foreach ( $data['players'] as $player ) {
			if ( $slug === $player['slug'] ) {
				$valid     = true;
				$feed_name = 'Spots & Stripes - ' . $player['name'];
			}
		}

		if ( ! $valid ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
			echo 'Calendar not found.';
			return;
		}

		$last_modified = ! empty( $data['source']['last_success_at'] ) && strtotime( $data['source']['last_success_at'] )
			? strtotime( $data['source']['last_success_at'] )
			: time();
		$calendar_state = get_option( self::CALENDAR_STATE_OPTION, array() );
		$calendar_state = is_array( $calendar_state ) ? $calendar_state : array();
		$state_changed  = false;
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//TYP Live//Spots and Stripes on Tour//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->ics_escape( $feed_name ),
			'X-WR-CALDESC:' . $this->ics_escape( 'Live match times and table allocations from DigitalPool.' ),
			'X-WR-TIMEZONE:Europe/London',
			'REFRESH-INTERVAL;VALUE=DURATION:PT30S',
			'X-PUBLISHED-TTL:PT30S',
		);

		foreach ( $data['schedule'] as $match ) {
			if ( ( 'all' !== $slug && $slug !== $match['player_slug'] ) || empty( $match['scheduled_time'] ) || ! strtotime( $match['scheduled_time'] ) ) {
				continue;
			}
			$start       = strtotime( $match['scheduled_time'] );
			$uid_source  = ! empty( $match['match_id'] ) ? 'match-' . absint( $match['match_id'] ) : md5( $match['player_slug'] . '|' . $match['tournament'] . '|' . $match['match_number'] . '|' . $match['round'] );
			$event_key   = $uid_source . '|' . sanitize_title( $match['player_slug'] );
			$event_hash  = md5( $match['scheduled_time'] . '|' . $match['table'] . '|' . $match['opponent_name'] . '|' . $match['tournament'] . '|' . $match['round'] . '|' . $match['tracked_player'] );
			$event_state = isset( $calendar_state[ $event_key ] ) && is_array( $calendar_state[ $event_key ] ) ? $calendar_state[ $event_key ] : array();
			if ( empty( $event_state ) || empty( $event_state['hash'] ) || $event_hash !== $event_state['hash'] ) {
				$event_state = array(
					'hash'     => $event_hash,
					'sequence' => isset( $event_state['sequence'] ) ? absint( $event_state['sequence'] ) + 1 : 0,
					'modified' => $last_modified,
				);
				$calendar_state[ $event_key ] = $event_state;
				$state_changed                = true;
			}
			$description = $match['tournament'] . ' - ' . $match['round'] . ' - Live table allocation from DigitalPool.';
			$lines[]     = 'BEGIN:VEVENT';
			$lines[]     = 'UID:typlive-' . $uid_source . '-' . sanitize_title( $match['player_slug'] ) . '@typlive.com';
			$lines[]     = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', $last_modified );
			$lines[]     = 'LAST-MODIFIED:' . gmdate( 'Ymd\THis\Z', absint( $event_state['modified'] ) );
			$lines[]     = 'SEQUENCE:' . absint( $event_state['sequence'] );
			$lines[]     = 'DTSTART:' . gmdate( 'Ymd\THis\Z', $start );
			$lines[]     = 'DTEND:' . gmdate( 'Ymd\THis\Z', $start + ( 2 * HOUR_IN_SECONDS ) );
			$lines[]     = 'SUMMARY:' . $this->ics_escape( $match['tracked_player'] . ' vs ' . $match['opponent_name'] );
			$lines[]     = 'DESCRIPTION:' . $this->ics_escape( $description );
			$lines[]     = 'LOCATION:' . $this->ics_escape( $match['table'] );
			$lines[]     = 'URL:' . esc_url_raw( home_url( '/upusa-schedule/' ) );
			$lines[]     = 'STATUS:CONFIRMED';
			$lines[]     = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';
		if ( $state_changed ) {
			update_option( self::CALENDAR_STATE_OPTION, $calendar_state, false );
		}

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="' . ( 'all' === $slug ? 'spots-stripes-on-tour' : 'spots-stripes-' . $slug ) . '.ics"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo implode( "\r\n", $lines ) . "\r\n";
	}

	private function ics_escape( $value ) {
		return str_replace(
			array( '\\', "\r\n", "\n", ',', ';' ),
			array( '\\\\', '\\n', '\\n', '\\,', '\\;' ),
			(string) $value
		);
	}

	private function calendar_data() {
		$cached = get_option( self::LATEST_SCHEDULE_OPTION, array() );
		if ( is_array( $cached ) && ! empty( $cached['saved_at'] ) && ! empty( $cached['data'] ) && is_array( $cached['data'] ) ) {
			$age = time() - absint( $cached['saved_at'] );
			if ( $age >= 0 && $age <= 90 ) {
				return $cached['data'];
			}
		}
		return $this->schedule_data();
	}

	private function schedule_data() {
		$rows           = array();
		$player_options = array();
		$scoreboards    = array();
		$pair_rows      = array();
		$api            = new TYPLive_DigitalPool();
		$source_successes = 0;
		$source_failures  = 0;

		foreach ( self::tracked_boards() as $slug => $board ) {
			$player_name            = (string) $board['player_name'];
			$player_options[ $slug ] = array(
				'slug' => $slug,
				'name' => $player_name,
			);
			$scoreboards[ $slug ] = array(
				'slug' => $slug,
				'name' => $player_name,
			);

			$schedule = $api->schedule_for_player( $player_name );
			if ( is_wp_error( $schedule ) ) {
				++$source_failures;
				continue;
			}
			++$source_successes;

			foreach ( $schedule as $match ) {
				$display_name = $player_name;
				$display_slug = $slug;
				if ( ! empty( $match['is_pair'] ) && ! empty( $match['player_name'] ) ) {
					$display_name = (string) $match['player_name'];
					$display_slug = ! empty( $match['competitor_slug'] ) ? (string) $match['competitor_slug'] : 'pair-' . sanitize_title( $display_name );
					$pair_key     = ( ! empty( $match['match_id'] ) ? 'id-' . absint( $match['match_id'] ) : md5( wp_json_encode( $match ) ) ) . '|' . $display_slug;
					if ( isset( $pair_rows[ $pair_key ] ) ) {
						continue;
					}
					$pair_rows[ $pair_key ]          = true;
					$player_options[ $display_slug ] = array(
						'slug' => $display_slug,
						'name' => $display_name,
					);
					$scoreboards[ $display_slug ] = array(
						'slug' => $display_slug,
						'name' => $display_name,
					);
					set_transient( 'typlive_pair_board_' . md5( $display_slug ), self::pair_board( $display_slug, $display_name ), DAY_IN_SECONDS );
				}

				$match['tracked_player'] = $display_name;
				$match['player_slug']    = $display_slug;
				$rows[]                  = $match;
			}
		}

		$players     = array_values( $player_options );
		$scoreboards = array_values( $scoreboards );
		usort(
			$players,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		usort(
			$scoreboards,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		usort(
			$rows,
			function ( $a, $b ) {
				$priority   = array( 'live' => 0, 'upcoming' => 1, 'result' => 2 );
				$a_priority = isset( $priority[ $a['state'] ] ) ? $priority[ $a['state'] ] : 1;
				$b_priority = isset( $priority[ $b['state'] ] ) ? $priority[ $b['state'] ] : 1;
				if ( $a_priority !== $b_priority ) {
					return $a_priority <=> $b_priority;
				}
				$a_time = ! empty( $a['scheduled_time'] ) ? strtotime( $a['scheduled_time'] ) : PHP_INT_MAX;
				$b_time = ! empty( $b['scheduled_time'] ) ? strtotime( $b['scheduled_time'] ) : PHP_INT_MAX;
				if ( 'result' === $a['state'] ) {
					return $b_time <=> $a_time;
				}
				return $a_time <=> $b_time;
			}
		);

		$source                   = TYPLive_DigitalPool::source_status();
		$source['available']      = 0 < $source_successes;
		$source['query_failures'] = $source_failures;

		return array(
			'event'       => 'Spots & Stripes on Tour',
			'players'     => $players,
			'scoreboards' => $scoreboards,
			'schedule'    => $rows,
			'source'      => $source,
			'updated_at'  => gmdate( 'c' ),
		);
	}

	/**
	 * Compare the current schedule with the previous monitor snapshot.
	 *
	 * @param bool $force Whether to bypass the 15-second DigitalPool cache.
	 * @return array<string,mixed>
	 */
	public function monitor_schedule_changes( $force = false ) {
		if ( $force ) {
			TYPLive_DigitalPool::clear_cache();
		}

		$data = $this->schedule_data();
		if ( empty( $data['source']['available'] ) ) {
			return array(
				'ok'      => false,
				'changes' => 0,
				'source'  => $data['source'],
			);
		}
		update_option(
			self::LATEST_SCHEDULE_OPTION,
			array(
				'saved_at' => time(),
				'data'     => $data,
			),
			false
		);

		$previous = get_option( self::SNAPSHOT_OPTION, array() );
		$previous = is_array( $previous ) ? $previous : array();
		$current  = $this->schedule_snapshot( $data['schedule'] );
		$changes  = array();
		$fields   = array(
			'scheduled_time' => 'Match time',
			'opponent_name'  => 'Opponent',
			'table'          => 'Table',
		);

		if ( ! empty( $previous ) ) {
			foreach ( $current as $key => $match ) {
				if ( ! isset( $previous[ $key ] ) ) {
					continue;
				}
				foreach ( $fields as $field => $label ) {
					$old = isset( $previous[ $key ][ $field ] ) ? (string) $previous[ $key ][ $field ] : '';
					$new = isset( $match[ $field ] ) ? (string) $match[ $field ] : '';
					if ( $old === $new ) {
						continue;
					}
					$old_display = 'scheduled_time' === $field ? ( ! empty( $previous[ $key ]['scheduled_label'] ) ? $previous[ $key ]['scheduled_label'] : 'Date & time TBC' ) : ( $old ? $old : 'TBC' );
					$new_display = 'scheduled_time' === $field ? ( ! empty( $match['scheduled_label'] ) ? $match['scheduled_label'] : 'Date & time TBC' ) : ( $new ? $new : 'TBC' );
					$changes[]   = array(
						'id'          => md5( $key . '|' . $field . '|' . $old . '|' . $new . '|' . microtime() ),
						'detected_at' => gmdate( 'c' ),
						'player'      => $match['player'],
						'field'       => $label,
						'old'         => $old_display,
						'new'         => $new_display,
					);
				}
			}
		}

		$snapshot = ! empty( $data['source']['query_failures'] ) ? array_merge( $previous, $current ) : $current;
		update_option( self::SNAPSHOT_OPTION, $snapshot, false );

		if ( ! empty( $changes ) ) {
			$alerts = get_option( self::ALERTS_OPTION, array() );
			$alerts = is_array( $alerts ) ? $alerts : array();
			update_option( self::ALERTS_OPTION, array_slice( array_merge( $changes, $alerts ), 0, 50 ), false );
		}

		return array(
			'ok'      => true,
			'changes' => count( $changes ),
			'source'  => $data['source'],
		);
	}

	private function schedule_snapshot( $schedule ) {
		$snapshot = array();
		foreach ( (array) $schedule as $match ) {
			$key = ! empty( $match['match_id'] )
				? 'match-' . absint( $match['match_id'] ) . '|' . sanitize_key( $match['player_slug'] )
				: md5( sanitize_key( $match['player_slug'] ) . '|' . (string) $match['tournament'] . '|' . (string) $match['match_number'] . '|' . (string) $match['round'] );
			$snapshot[ $key ] = array(
				'player'          => sanitize_text_field( $match['tracked_player'] ),
				'opponent_name'   => sanitize_text_field( $match['opponent_name'] ),
				'table'           => sanitize_text_field( $match['table'] ),
				'scheduled_time'  => sanitize_text_field( $match['scheduled_time'] ),
				'scheduled_label' => sanitize_text_field( $match['scheduled_label'] ),
			);
		}
		return $snapshot;
	}

	private function browser_config( $slug, $data ) {
		$settings = self::settings();
		return array(
			'endpoint'       => esc_url_raw( rest_url( 'typlive/v1/score/' . $slug ) ),
			'refreshSeconds' => max( 3, min( 60, absint( $settings['refresh_seconds'] ) ) ),
			'initial'        => $data,
		);
	}

	private function get_board( $slug ) {
		$settings = self::settings();
		$boards   = isset( $settings['boards'] ) && is_array( $settings['boards'] ) ? $settings['boards'] : array();

		if ( isset( $boards[ $slug ] ) && is_array( $boards[ $slug ] ) ) {
			return $boards[ $slug ];
		}

		if ( 0 === strpos( $slug, 'pair-' ) ) {
			$pair = get_transient( 'typlive_pair_board_' . md5( $slug ) );
			if ( is_array( $pair ) ) {
				return $pair;
			}
		}

		return null;
	}
}

register_activation_hook( __FILE__, array( 'TYPLive_Scoreboards', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TYPLive_Scoreboards', 'deactivate' ) );
TYPLive_Scoreboards::instance();

