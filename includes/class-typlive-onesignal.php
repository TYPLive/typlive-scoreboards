<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TYPLive_OneSignal {
	const EVENT_TAG_KEY   = 'typlive_event';
	const EVENT_TAG_VALUE = 'upusa_2026';
	const STATUS_OPTION   = 'typlive_onesignal_status';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	public static function is_configured( $settings ) {
		return ! empty( $settings['onesignal_enabled'] )
			&& ! empty( $settings['onesignal_app_id'] )
			&& ! empty( $settings['onesignal_api_key'] );
	}

	/** @return array<string,mixed> */
	public static function status() {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Send a push notification to devices that opted into this event.
	 *
	 * @param string              $heading Notification heading.
	 * @param string              $content Notification body.
	 * @param array<string,mixed> $data    Extra notification data.
	 * @return array<string,mixed>
	 */
	public function send( $heading, $content, $data = array() ) {
		if ( ! self::is_configured( $this->settings ) ) {
			return array(
				'ok'    => false,
				'error' => 'OneSignal is not configured.',
			);
		}

		$payload = array(
			'app_id'         => (string) $this->settings['onesignal_app_id'],
			'target_channel' => 'push',
			'name'           => 'Spots & Stripes match alert',
			'headings'       => array( 'en' => sanitize_text_field( $heading ) ),
			'contents'       => array( 'en' => sanitize_text_field( $content ) ),
			'filters'        => array(
				array(
					'field'    => 'tag',
					'key'      => self::EVENT_TAG_KEY,
					'relation' => '=',
					'value'    => self::EVENT_TAG_VALUE,
				),
			),
			'url'            => home_url( '/upusa-schedule/' ),
			'data'           => array_merge(
				array( 'event' => self::EVENT_TAG_VALUE ),
				is_array( $data ) ? $data : array()
			),
		);

		$site_icon = get_site_icon_url( 256 );
		if ( $site_icon ) {
			$payload['chrome_web_icon'] = esc_url_raw( $site_icon );
		}

		$response = wp_remote_post(
			'https://api.onesignal.com/notifications',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Key ' . (string) $this->settings['onesignal_api_key'],
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->record_failure( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ( is_array( $body ) && ! empty( $body['errors'] ) ) ) {
			$error = is_array( $body ) && ! empty( $body['errors'] )
				? wp_json_encode( $body['errors'] )
				: 'OneSignal returned HTTP ' . absint( $code ) . '.';
			return $this->record_failure( $error );
		}

		$status = array(
			'ok'              => true,
			'last_success_at' => gmdate( 'c' ),
			'last_message_id' => is_array( $body ) && ! empty( $body['id'] ) ? sanitize_text_field( $body['id'] ) : '',
			'last_error'      => '',
		);
		update_option( self::STATUS_OPTION, $status, false );
		return $status;
	}

	/**
	 * @param string $message Error message.
	 * @return array<string,mixed>
	 */
	private function record_failure( $message ) {
		$status = array(
			'ok'              => false,
			'last_attempt_at' => gmdate( 'c' ),
			'last_error'      => sanitize_text_field( (string) $message ),
		);
		update_option( self::STATUS_OPTION, $status, false );
		return $status;
	}
}
