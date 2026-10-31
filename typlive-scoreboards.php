<?php
/**
 * Plugin Name: TYP Live Scoreboards
 * Plugin URI: https://github.com/TYPLive/typlive-scoreboards
 * Description: Streamlabs-ready live pool scoreboards with stable player URLs.
 * Version: 0.3.7
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

define( 'TYPLIVE_SCOREBOARDS_VERSION', '0.3.7' );
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
		add_action( 'init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
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

		self::instance()->register_routes();
		flush_rewrite_rules();
		update_option( 'typlive_scoreboards_rewrite_version', TYPLIVE_SCOREBOARDS_VERSION, false );
	}

	public static function deactivate() {
		flush_rewrite_rules();
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
		$settings['boards'] = array_merge( $saved_boards, self::tracked_boards() );
		return $settings;
	}

	public function register_routes() {
		add_rewrite_rule( '^scoreboard-([a-z0-9-]+)/?$', 'index.php?typlive_scoreboard=$matches[1]', 'top' );
		add_rewrite_rule( '^upusa-schedule/?$', 'index.php?typlive_upusa_schedule=1', 'top' );
	}

	public function maybe_flush_rewrite_rules() {
		if ( TYPLIVE_SCOREBOARDS_VERSION !== get_option( 'typlive_scoreboards_rewrite_version' ) ) {
			flush_rewrite_rules( false );
			update_option( 'typlive_scoreboards_rewrite_version', TYPLIVE_SCOREBOARDS_VERSION, false );
		}
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'typlive_scoreboard';
		$vars[] = 'typlive_upusa_schedule';
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
			'refreshSeconds' => 15,
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		include TYPLIVE_SCOREBOARDS_DIR . 'templates/schedule-page.php';
	}

	private function schedule_data() {
		$rows           = array();
		$player_options = array();
		$scoreboards    = array();
		$pair_rows      = array();
		$api            = new TYPLive_DigitalPool();

		foreach ( self::tracked_boards() as $slug => $board ) {
			$player_name            = (string) $board['player_name'];
			$player_options[ $slug ] = array(
				'slug' => $slug,
				'name' => $player_name,
			);
			$scoreboards[] = array(
				'slug' => $slug,
				'name' => $player_name,
			);

			$schedule = $api->schedule_for_player( $player_name );
			if ( is_wp_error( $schedule ) ) {
				continue;
			}

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
				}

				$match['tracked_player'] = $display_name;
				$match['player_slug']    = $display_slug;
				$rows[]                  = $match;
			}
		}

		$players = array_values( $player_options );
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

		return array(
			'event'      => 'Spots & Stripes on Tour',
			'players'    => $players,
			'scoreboards' => $scoreboards,
			'schedule'   => $rows,
			'updated_at' => gmdate( 'c' ),
		);
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

		return isset( $boards[ $slug ] ) && is_array( $boards[ $slug ] ) ? $boards[ $slug ] : null;
	}
}

register_activation_hook( __FILE__, array( 'TYPLive_Scoreboards', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TYPLive_Scoreboards', 'deactivate' ) );
TYPLive_Scoreboards::instance();

