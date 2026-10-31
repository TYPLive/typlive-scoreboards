<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connect this plugin to its public GitHub Releases feed.
 */
final class TYPLive_Updater {
	const REPOSITORY = 'TYPLive/typlive-scoreboards';
	const API_URL    = 'https://api.github.com/repos/TYPLive/typlive-scoreboards/releases/latest';
	const CACHE_KEY  = 'typlive_scoreboards_github_release';

	/** @var string */
	private $plugin_basename;

	public function __construct( $plugin_file ) {
		$this->plugin_basename = plugin_basename( $plugin_file );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( $this, 'enable_auto_updates' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_upgrade' ), 10, 2 );
	}

	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked[ $this->plugin_basename ] ) ) {
			return $transient;
		}

		$release = $this->latest_release();
		if ( ! $release || ! version_compare( $release['version'], TYPLIVE_SCOREBOARDS_VERSION, '>' ) ) {
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'id'           => 'https://github.com/' . self::REPOSITORY,
			'slug'         => 'typlive-scoreboards',
			'plugin'       => $this->plugin_basename,
			'new_version'  => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'requires'     => '6.2',
			'requires_php' => '7.4',
			'tested'       => '6.8',
		);

		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'typlive-scoreboards' !== $args->slug ) {
			return $result;
		}

		$release = $this->latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'TYP Live Scoreboards',
			'slug'          => 'typlive-scoreboards',
			'version'       => $release['version'],
			'author'        => '<a href="https://typlive.com/">TYP Live</a>',
			'homepage'      => 'https://github.com/' . self::REPOSITORY,
			'requires'      => '6.2',
			'requires_php'  => '7.4',
			'tested'        => '6.8',
			'last_updated'  => $release['published_at'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => 'Live Spots & Stripes on Tour scoreboards and a combined tracked-player schedule for TYP Live.',
				'changelog'   => nl2br( esc_html( $release['body'] ) ),
			),
		);
	}

	public function enable_auto_updates( $update, $item ) {
		if ( is_object( $item ) && ! empty( $item->plugin ) && $this->plugin_basename === $item->plugin ) {
			return true;
		}

		return $update;
	}

	public function clear_cache_after_upgrade( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] || empty( $options['plugins'] ) ) {
			return;
		}

		if ( in_array( $this->plugin_basename, (array) $options['plugins'], true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	private function latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return ! empty( $cached['version'] ) ? $cached : null;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'headers'     => array(
					'Accept'               => 'application/vnd.github+json',
					'User-Agent'           => 'TYP-Live-Scoreboards/' . TYPLIVE_SCOREBOARDS_VERSION . ' (WordPress)',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || empty( $data['assets'] ) ) {
			return null;
		}

		$package = '';
		foreach ( $data['assets'] as $asset ) {
			if ( ! empty( $asset['name'] ) && 'typlive-scoreboards.zip' === $asset['name'] && ! empty( $asset['browser_download_url'] ) ) {
				$package = esc_url_raw( $asset['browser_download_url'] );
				break;
			}
		}

		$version = preg_replace( '/^v/i', '', sanitize_text_field( $data['tag_name'] ) );
		if ( '' === $package || ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return null;
		}

		$release = array(
			'version'      => $version,
			'package'      => $package,
			'html_url'     => ! empty( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : 'https://github.com/' . self::REPOSITORY . '/releases',
			'published_at' => ! empty( $data['published_at'] ) ? sanitize_text_field( $data['published_at'] ) : gmdate( 'c' ),
			'body'         => ! empty( $data['body'] ) ? sanitize_textarea_field( $data['body'] ) : 'Maintenance and feature update.',
		);

		set_site_transient( self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}
}
