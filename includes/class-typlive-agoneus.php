<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TYPLive_Agoneus {
	const API_BASE = 'https://allscoreslive.com/asl/api/';
	const ORG_ID   = 7;

	public function tournaments() {
		$cached = get_transient( 'typlive_agoneus_tournaments_v1' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$seasons = $this->request( 'admin/readsaisons.php?org=' . self::ORG_ID );
		if ( is_wp_error( $seasons ) ) {
			return $seasons;
		}

		$tournaments = array();
		foreach ( $seasons as $season ) {
			if ( empty( $season['saison'] ) ) {
				continue;
			}

			$query = add_query_arg(
				array(
					'org'    => self::ORG_ID,
					'l1'     => 0,
					'l2'     => 0,
					'l3'     => 0,
					'l4'     => 0,
					'saison' => sanitize_text_field( $season['saison'] ),
				),
				'admin/readalldates.php'
			);
			$events = $this->request( $query );
			if ( is_wp_error( $events ) ) {
				continue;
			}

			foreach ( $events as $event ) {
				if ( empty( $event['code'] ) || empty( $event['name'] ) ) {
					continue;
				}

				$start = '';
				$end   = '';
				if ( ! empty( $event['dates'][0] ) && is_array( $event['dates'][0] ) ) {
					$start = isset( $event['dates'][0]['startdate'] ) ? (string) $event['dates'][0]['startdate'] : '';
					$end   = isset( $event['dates'][0]['enddate'] ) ? (string) $event['dates'][0]['enddate'] : '';
				}

				$tournaments[] = array(
					'id'     => absint( $event['code'] ),
					'name'   => sanitize_text_field( $event['name'] ),
					'venue'  => isset( $event['venue'] ) ? sanitize_text_field( $event['venue'] ) : '',
					'season' => sanitize_text_field( $season['saison'] ),
					'state'  => isset( $event['state'] ) ? sanitize_key( strtolower( $event['state'] ) ) : '',
					'start'  => $start,
					'end'    => $end,
				);
			}
		}

		usort(
			$tournaments,
			function ( $a, $b ) {
				return strcmp( $b['start'], $a['start'] );
			}
		);

		set_transient( 'typlive_agoneus_tournaments_v1', $tournaments, 15 * MINUTE_IN_SECONDS );
		return $tournaments;
	}

	public function tournament( $event_id ) {
		$events = $this->tournaments();
		if ( is_wp_error( $events ) ) {
			return $events;
		}

		foreach ( $events as $event ) {
			if ( absint( $event['id'] ) === absint( $event_id ) ) {
				return $event;
			}
		}

		return new WP_Error( 'typlive_event_missing', 'That tournament is no longer available from the scoring provider.' );
	}

	public function players( $event_id ) {
		$event_id = absint( $event_id );
		$cache    = 'typlive_players_' . $event_id . '_v1';
		$cached   = get_transient( $cache );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$categories = $this->categories( $event_id );
		if ( is_wp_error( $categories ) ) {
			return $categories;
		}

		$players = array();
		foreach ( $categories as $category ) {
			if ( isset( $category['enabled'] ) && 1 !== absint( $category['enabled'] ) ) {
				continue;
			}

			$category_id = isset( $category['categ'] ) ? absint( $category['categ'] ) : 0;
			if ( ! $category_id ) {
				continue;
			}

			$entries = $this->request(
				add_query_arg(
					array( 'event' => $event_id, 'categ' => $category_id ),
					'tournament/readsingles.php'
				)
			);
			if ( ! is_wp_error( $entries ) && is_array( $entries ) ) {
				$this->collect_players( $entries, $players );
			} else {
				$teams = $this->request(
					add_query_arg(
						array( 'event' => $event_id, 'categ' => $category_id ),
						'tournament/readteams.php'
					)
				);
				if ( ! is_wp_error( $teams ) && is_array( $teams ) ) {
					$this->collect_players( $teams, $players );
				}
			}
		}

		$players = array_values( $players );
		usort(
			$players,
			function ( $a, $b ) {
				$last = strcasecmp( $a['last'], $b['last'] );
				return 0 !== $last ? $last : strcasecmp( $a['first'], $b['first'] );
			}
		);

		set_transient( $cache, $players, 10 * MINUTE_IN_SECONDS );
		return $players;
	}

	public function categories( $event_id ) {
		return $this->request(
			add_query_arg( 'event', absint( $event_id ), 'tournament/readparms.php' ),
			HOUR_IN_SECONDS
		);
	}

	public function live_matches( $event_id ) {
		return $this->request(
			add_query_arg( 'event', absint( $event_id ), 'live/readalllives.php' ),
			3
		);
	}

	public function category( $category_id ) {
		return $this->request(
			add_query_arg( 'code', absint( $category_id ), 'category/readone.php' ),
			HOUR_IN_SECONDS
		);
	}

	private function collect_players( $node, &$players ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		if ( isset( $node['i'], $node['n'], $node['p'] ) && absint( $node['i'] ) > 0 ) {
			$id = absint( $node['i'] );
			$players[ $id ] = array(
				'id'      => $id,
				'first'   => sanitize_text_field( $node['p'] ),
				'last'    => sanitize_text_field( $node['n'] ),
				'country' => isset( $node['c'] ) ? sanitize_text_field( $node['c'] ) : '',
			);
		}

		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				$this->collect_players( $value, $players );
			}
		}
	}

	private function request( $path, $ttl = 5 * MINUTE_IN_SECONDS ) {
		$url       = self::API_BASE . ltrim( $path, '/' );
		$cache_key = 'typlive_api_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 2,
				'headers'     => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'TYP-Live-Scoreboards/' . TYPLIVE_SCOREBOARDS_VERSION . ' (WordPress)',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'typlive_provider_http', 'The live-scoring provider returned HTTP ' . absint( $status ) . '.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'typlive_provider_json', 'The live-scoring provider returned invalid data.' );
		}

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'typlive_provider_error', sanitize_text_field( $data['error'] ) );
		}

		set_transient( $cache_key, $data, max( 3, absint( $ttl ) ) );
		return $data;
	}
}

