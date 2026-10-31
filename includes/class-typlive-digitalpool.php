<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TYPLive_DigitalPool {
	const API_URL    = 'https://api-nyc.digitalpool.com/v1/graphql';
	const EVENT_ID   = 2693;
	const EVENT_SLUG = '2026-ultimate-pool-usa-league-grand-finals';

	/**
	 * Return the most relevant DigitalPool match for a player.
	 *
	 * @param string $player_name Player display name.
	 * @return array|null|WP_Error
	 */
	public function match_for_player( $player_name ) {
		$matches = $this->matches_for_player( $player_name );
		if ( is_wp_error( $matches ) ) {
			return $matches;
		}

		return $this->choose_match( $matches, $player_name );
	}

	/**
	 * Return upcoming and in-progress scheduled matches for a player.
	 *
	 * @param string $player_name Player display name.
	 * @return array|WP_Error
	 */
	public function schedule_for_player( $player_name ) {
		$matches = $this->matches_for_player( $player_name );
		if ( is_wp_error( $matches ) ) {
			return $matches;
		}

		$target_name = $this->normalize_name( $player_name );
		$schedule    = array();
		foreach ( $matches as $match ) {
			if ( ! $this->is_exact_player_match( $match, $target_name ) ) {
				continue;
			}

			$status = isset( $match['status'] ) ? strtoupper( sanitize_key( $match['status'] ) ) : '';
			if ( 'COMPLETED' === $status || 'CANCELLED' === $status ) {
				continue;
			}

			$schedule[] = $this->format_match( $match, $player_name );
		}

		usort(
			$schedule,
			function ( $a, $b ) {
				$a_live = ! empty( $a['live'] );
				$b_live = ! empty( $b['live'] );
				if ( $a_live !== $b_live ) {
					return $a_live ? -1 : 1;
				}
				$a_time = ! empty( $a['scheduled_time'] ) ? strtotime( $a['scheduled_time'] ) : PHP_INT_MAX;
				$b_time = ! empty( $b['scheduled_time'] ) ? strtotime( $b['scheduled_time'] ) : PHP_INT_MAX;
				return $a_time <=> $b_time;
			}
		);

		return $schedule;
	}

	private function matches_for_player( $player_name ) {
		$player_name = sanitize_text_field( $player_name );
		if ( '' === trim( $player_name ) ) {
			return array();
		}

		$cache_key = 'typlive_dp_matches_' . md5( self::EVENT_ID . '|' . $this->normalize_name( $player_name ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = <<<'GRAPHQL'
query PlayerSchedule($event_id: Int!, $player: String!) {
  tournament_brackets(
    where: {
      tournament: { event_id: { _eq: $event_id } }
      _or: [
        { challenger1_name: { _ilike: $player } }
        { challenger2_name: { _ilike: $player } }
      ]
    }
    order_by: [{ scheduled_time: asc }, { match_number: asc }]
  ) {
    id
    match_number
    round
    scheduled_time
    scheduled_table_id
    table_id
    table_name
    status
    challenger1_name
    challenger1_score
    challenger2_name
    challenger2_score
    tournament {
      id
      name
    }
    scheduled_table {
      slug
      label
    }
  }
}
GRAPHQL;

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout'     => 20,
				'redirection' => 2,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'User-Agent'   => 'TYP-Live-Scoreboards/' . TYPLIVE_SCOREBOARDS_VERSION . ' (WordPress)',
				),
				'body'        => wp_json_encode(
					array(
						'query'     => $query,
						'variables' => array(
							'event_id' => self::EVENT_ID,
							'player'   => '%' . $player_name . '%',
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'typlive_digitalpool_http', 'DigitalPool returned HTTP ' . absint( $status ) . '.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'typlive_digitalpool_json', 'DigitalPool returned invalid data.' );
		}

		if ( ! empty( $data['errors'][0]['message'] ) ) {
			return new WP_Error( 'typlive_digitalpool_graphql', sanitize_text_field( $data['errors'][0]['message'] ) );
		}

		$matches = isset( $data['data']['tournament_brackets'] ) && is_array( $data['data']['tournament_brackets'] ) ? $data['data']['tournament_brackets'] : array();
		set_transient( $cache_key, $matches, 15 );
		return $matches;
	}

	private function choose_match( $matches, $player_name ) {
		$exact       = array();
		$target_name = $this->normalize_name( $player_name );
		$now         = time();

		foreach ( $matches as $match ) {
			if ( ! $this->is_exact_player_match( $match, $target_name ) ) {
				continue;
			}

			$status = isset( $match['status'] ) ? strtoupper( sanitize_key( $match['status'] ) ) : '';
			if ( 'COMPLETED' === $status || 'CANCELLED' === $status ) {
				continue;
			}

			$scheduled = ! empty( $match['scheduled_time'] ) ? strtotime( $match['scheduled_time'] ) : false;
			$priority  = 4;
			if ( 'IN_PROGRESS' === $status ) {
				$priority = 0;
			} elseif ( $scheduled && $scheduled <= $now + ( 2 * HOUR_IN_SECONDS ) && $scheduled >= $now - ( 3 * HOUR_IN_SECONDS ) ) {
				$priority = 1;
			} elseif ( $scheduled && $scheduled > $now ) {
				$priority = 2;
			} elseif ( 'SCHEDULED' === $status ) {
				$priority = 3;
			}

			$match['_priority'] = $priority;
			$match['_time']     = $scheduled ? $scheduled : PHP_INT_MAX;
			$exact[]            = $match;
		}

		if ( empty( $exact ) ) {
			return null;
		}

		usort(
			$exact,
			function ( $a, $b ) {
				if ( $a['_priority'] !== $b['_priority'] ) {
					return $a['_priority'] <=> $b['_priority'];
				}
				return $a['_time'] <=> $b['_time'];
			}
		);

		return $this->format_match( $exact[0], $player_name );
	}

	private function is_exact_player_match( $match, $target_name ) {
		if ( ! is_array( $match ) ) {
			return false;
		}

		$side1 = isset( $match['challenger1_name'] ) ? (string) $match['challenger1_name'] : '';
		$side2 = isset( $match['challenger2_name'] ) ? (string) $match['challenger2_name'] : '';
		return $target_name === $this->normalize_name( $side1 ) || $target_name === $this->normalize_name( $side2 );
	}

	private function format_match( $match, $player_name ) {
		$side1           = isset( $match['challenger1_name'] ) ? sanitize_text_field( $match['challenger1_name'] ) : '';
		$side2           = isset( $match['challenger2_name'] ) ? sanitize_text_field( $match['challenger2_name'] ) : '';
		$player_is_side1 = $this->normalize_name( $player_name ) === $this->normalize_name( $side1 );
		$status          = isset( $match['status'] ) ? strtoupper( sanitize_key( $match['status'] ) ) : 'SCHEDULED';
		$round           = ! empty( $match['round'] ) ? 'Round ' . absint( $match['round'] ) : 'Match';
		$table           = isset( $match['table_name'] ) ? sanitize_text_field( $match['table_name'] ) : '';

		if ( '' === $table && ! empty( $match['scheduled_table']['label'] ) ) {
			$table = sanitize_text_field( $match['scheduled_table']['label'] );
		}
		if ( '' === $table && ! empty( $match['scheduled_table']['slug'] ) ) {
			$table = ucwords( str_replace( '-', ' ', sanitize_title( $match['scheduled_table']['slug'] ) ) );
		}

		$scheduled_time  = isset( $match['scheduled_time'] ) ? sanitize_text_field( $match['scheduled_time'] ) : '';
		$scheduled_label = 'Date & time TBC';
		if ( $scheduled_time && strtotime( $scheduled_time ) ) {
			$scheduled_label = wp_date( 'D j M · g:i a', strtotime( $scheduled_time ), new DateTimeZone( 'America/New_York' ) ) . ' ET';
		}

		$player_score   = $player_is_side1 ? $match['challenger1_score'] : $match['challenger2_score'];
		$opponent_score = $player_is_side1 ? $match['challenger2_score'] : $match['challenger1_score'];

		return array(
			'match_id'       => isset( $match['id'] ) ? absint( $match['id'] ) : 0,
			'match_number'   => isset( $match['match_number'] ) ? absint( $match['match_number'] ) : 0,
			'round'          => $round,
			'status'         => $status,
			'live'           => 'IN_PROGRESS' === $status,
			'scheduled_time' => $scheduled_time,
			'scheduled_label' => $scheduled_label,
			'table'          => $table ? $table : 'Table TBC',
			'tournament'     => ! empty( $match['tournament']['name'] ) ? sanitize_text_field( $match['tournament']['name'] ) : '',
			'player_name'    => $player_is_side1 ? $side1 : $side2,
			'player_score'   => is_numeric( $player_score ) ? absint( $player_score ) : 0,
			'opponent_name'  => $player_is_side1 ? $side2 : $side1,
			'opponent_score' => is_numeric( $opponent_score ) ? absint( $opponent_score ) : 0,
		);
	}

	private function normalize_name( $name ) {
		$name = remove_accents( strtolower( trim( (string) $name ) ) );
		$name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
		return trim( preg_replace( '/\s+/', ' ', $name ) );
	}
}
