<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TYPLive_DigitalPool {
	const API_URL    = 'https://api-nyc.digitalpool.com/v1/graphql';
	const EVENT_ID   = 2693;
	const EVENT_SLUG = '2026-ultimate-pool-usa-league-grand-finals';
	const PAIR_TOURNAMENT_IDS = array( 159103, 159104 );
	const STATUS_OPTION = 'typlive_digitalpool_status';

	/** @var array<string,array<int,string>> */
	private $competitor_name_cache = array();

	/** @var array<int,array<string,mixed>>|null */
	private $pair_roster = null;

	/**
	 * Return the most recent DigitalPool connection state.
	 *
	 * @return array<string,mixed>
	 */
	public static function source_status() {
		$status = get_option( self::STATUS_OPTION, array() );
		return wp_parse_args(
			is_array( $status ) ? $status : array(),
			array(
				'ok'              => false,
				'last_attempt_at' => '',
				'last_success_at' => '',
				'last_error'      => '',
			)
		);
	}

	/** Clear the short-lived DigitalPool caches before a manual/admin check. */
	public static function clear_cache() {
		$api = new self();
		foreach ( TYPLive_Scoreboards::tracked_boards() as $board ) {
			$player_name = isset( $board['player_name'] ) ? (string) $board['player_name'] : '';
			if ( '' === trim( $player_name ) ) {
				continue;
			}
			delete_transient( 'typlive_dp_matches_' . md5( self::EVENT_ID . '|' . $api->normalize_name( $player_name ) ) );
		}
		delete_transient( 'typlive_dp_pair_roster_' . md5( implode( '|', self::PAIR_TOURNAMENT_IDS ) ) );
	}

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
	 * Return live, upcoming and completed matches for a player.
	 *
	 * @param string $player_name Player display name.
	 * @return array|WP_Error
	 */
	public function schedule_for_player( $player_name ) {
		$matches = $this->matches_for_player( $player_name );
		if ( is_wp_error( $matches ) ) {
			return $matches;
		}

		$competitor_names = $this->competitor_names_for_player( $player_name );
		$schedule         = array();
		foreach ( $matches as $match ) {
			if ( ! $this->is_exact_player_match( $match, $competitor_names ) ) {
				continue;
			}

			$status = isset( $match['status'] ) ? strtoupper( sanitize_key( $match['status'] ) ) : '';
			if ( in_array( $status, array( 'CANCELLED', 'CANCELED' ), true ) ) {
				continue;
			}

			$schedule[] = $this->format_match( $match, $player_name, $competitor_names );
		}

		usort(
			$schedule,
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

		return $schedule;
	}

	private function matches_for_player( $player_name ) {
		$player_name = sanitize_text_field( $player_name );
		if ( '' === trim( $player_name ) ) {
			return array();
		}
		$competitor_names = $this->competitor_names_for_player( $player_name );

		$cache_key = 'typlive_dp_matches_' . md5( self::EVENT_ID . '|' . $this->normalize_name( $player_name ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = <<<'GRAPHQL'
query PlayerSchedule($event_id: Int!, $player: String!, $competitors: [String!]!) {
  tournament_brackets(
    where: {
      tournament: { event_id: { _eq: $event_id } }
      _or: [
        { challenger1_name: { _ilike: $player } }
        { challenger2_name: { _ilike: $player } }
        { challenger1_name: { _in: $competitors } }
        { challenger2_name: { _in: $competitors } }
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
							'event_id'    => self::EVENT_ID,
							'player'      => '%' . $player_name . '%',
							'competitors' => array_values( $competitor_names ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->record_status( false, $response->get_error_message() );
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			$this->record_status( false, 'DigitalPool returned HTTP ' . absint( $status ) . '.' );
			return new WP_Error( 'typlive_digitalpool_http', 'DigitalPool returned HTTP ' . absint( $status ) . '.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			$this->record_status( false, 'DigitalPool returned invalid data.' );
			return new WP_Error( 'typlive_digitalpool_json', 'DigitalPool returned invalid data.' );
		}

		if ( ! empty( $data['errors'][0]['message'] ) ) {
			$this->record_status( false, sanitize_text_field( $data['errors'][0]['message'] ) );
			return new WP_Error( 'typlive_digitalpool_graphql', sanitize_text_field( $data['errors'][0]['message'] ) );
		}

		$matches = isset( $data['data']['tournament_brackets'] ) && is_array( $data['data']['tournament_brackets'] ) ? $data['data']['tournament_brackets'] : array();
		$this->record_status( true );
		set_transient( $cache_key, $matches, 15 );
		return $matches;
	}

	private function record_status( $ok, $message = '' ) {
		$status       = self::source_status();
		$last_attempt = ! empty( $status['last_attempt_at'] ) ? strtotime( $status['last_attempt_at'] ) : 0;
		$same_state   = (bool) $status['ok'] === (bool) $ok;
		if ( $same_state && $last_attempt && time() - $last_attempt < 30 && ( $ok || $status['last_error'] === sanitize_text_field( $message ) ) ) {
			return;
		}

		$status['ok']              = (bool) $ok;
		$status['last_attempt_at'] = gmdate( 'c' );
		if ( $ok ) {
			$status['last_success_at'] = $status['last_attempt_at'];
			$status['last_error']      = '';
		} else {
			$status['last_error'] = sanitize_text_field( $message );
		}
		update_option( self::STATUS_OPTION, $status, false );
	}

	private function choose_match( $matches, $player_name ) {
		$exact            = array();
		$competitor_names = $this->competitor_names_for_player( $player_name );
		$now              = time();

		foreach ( $matches as $match ) {
			if ( ! $this->is_exact_player_match( $match, $competitor_names ) ) {
				continue;
			}

			$status = isset( $match['status'] ) ? strtoupper( sanitize_key( $match['status'] ) ) : '';
			if ( in_array( $status, array( 'COMPLETED', 'COMPLETE', 'FINISHED', 'FINAL', 'CANCELLED', 'CANCELED' ), true ) ) {
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

		return $this->format_match( $exact[0], $player_name, $competitor_names );
	}

	private function is_exact_player_match( $match, $competitor_names ) {
		if ( ! is_array( $match ) ) {
			return false;
		}

		$side1   = isset( $match['challenger1_name'] ) ? (string) $match['challenger1_name'] : '';
		$side2   = isset( $match['challenger2_name'] ) ? (string) $match['challenger2_name'] : '';
		$targets = array_map( array( $this, 'normalize_name' ), (array) $competitor_names );
		return in_array( $this->normalize_name( $side1 ), $targets, true ) || in_array( $this->normalize_name( $side2 ), $targets, true );
	}

	private function format_match( $match, $player_name, $competitor_names = array() ) {
		$side1           = isset( $match['challenger1_name'] ) ? sanitize_text_field( $match['challenger1_name'] ) : '';
		$side2           = isset( $match['challenger2_name'] ) ? sanitize_text_field( $match['challenger2_name'] ) : '';
		$targets         = array_map( array( $this, 'normalize_name' ), $competitor_names ? $competitor_names : array( $player_name ) );
		$player_is_side1 = in_array( $this->normalize_name( $side1 ), $targets, true );
		$player_side     = $player_is_side1 ? $side1 : $side2;
		$opponent_side   = $player_is_side1 ? $side2 : $side1;
		$is_pair         = false !== strpos( $player_side, '&' );
		$player_display  = $this->display_competitor_name( $player_side );
		$opponent_display = $this->display_competitor_name( $opponent_side );
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
		$score_available = is_numeric( $player_score ) && is_numeric( $opponent_score );
		$live            = in_array( $status, array( 'IN_PROGRESS', 'INPROGRESS', 'LIVE' ), true );
		$completed       = in_array( $status, array( 'COMPLETED', 'COMPLETE', 'FINISHED', 'FINAL' ), true );
		$state           = $live ? 'live' : ( $completed ? 'result' : 'upcoming' );
		$outcome         = '';
		if ( $completed && $score_available ) {
			if ( absint( $player_score ) > absint( $opponent_score ) ) {
				$outcome = 'Win';
			} elseif ( absint( $player_score ) < absint( $opponent_score ) ) {
				$outcome = 'Loss';
			} else {
				$outcome = 'Draw';
			}
		}

		return array(
			'match_id'       => isset( $match['id'] ) ? absint( $match['id'] ) : 0,
			'match_number'   => isset( $match['match_number'] ) ? absint( $match['match_number'] ) : 0,
			'round'          => $round,
			'status'         => $status,
			'live'           => $live,
			'completed'      => $completed,
			'state'          => $state,
			'score_available' => $score_available,
			'result_outcome' => $outcome,
			'scheduled_time' => $scheduled_time,
			'scheduled_label' => $scheduled_label,
			'table'          => $table ? $table : 'Table TBC',
			'tournament'     => ! empty( $match['tournament']['name'] ) ? sanitize_text_field( $match['tournament']['name'] ) : '',
			'is_pair'        => $is_pair,
			'competitor_slug' => $is_pair ? 'pair-' . sanitize_title( $player_display ) : '',
			'player_name'    => $player_display,
			'player_score'   => is_numeric( $player_score ) ? absint( $player_score ) : 0,
			'opponent_name'  => $opponent_display,
			'opponent_score' => is_numeric( $opponent_score ) ? absint( $opponent_score ) : 0,
		);
	}

	private function display_competitor_name( $competitor_name ) {
		$competitor_name = sanitize_text_field( $competitor_name );
		if ( '' === $competitor_name || false === strpos( $competitor_name, '&' ) ) {
			return $competitor_name;
		}

		$members     = array();
		$team_target = $this->normalize_name( $competitor_name );
		foreach ( $this->pair_roster() as $entry ) {
			if ( empty( $entry['team']['name'] ) || $team_target !== $this->normalize_name( $entry['team']['name'] ) ) {
				continue;
			}
			$member = ! empty( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
			if ( '' === $member && ! empty( $entry['player']['name'] ) ) {
				$member = sanitize_text_field( $entry['player']['name'] );
			}
			if ( '' !== $member ) {
				$members[ $this->normalize_name( $member ) ] = $member;
			}
		}

		if ( count( $members ) < 2 ) {
			return $competitor_name;
		}

		$ordered = array();
		$used    = array();
		$parts   = preg_split( '/\s*&\s*/', $competitor_name );
		foreach ( $parts as $part ) {
			foreach ( $members as $key => $member ) {
				if ( isset( $used[ $key ] ) || ! $this->abbreviated_name_matches( $part, $member ) ) {
					continue;
				}
				$ordered[]    = $member;
				$used[ $key ] = true;
				break;
			}
		}
		foreach ( $members as $key => $member ) {
			if ( ! isset( $used[ $key ] ) ) {
				$ordered[] = $member;
			}
		}

		return implode( ' & ', $ordered );
	}

	private function abbreviated_name_matches( $abbreviated, $full_name ) {
		$short = explode( ' ', $this->normalize_name( $abbreviated ) );
		$full  = explode( ' ', $this->normalize_name( $full_name ) );
		if ( empty( $short[0] ) || empty( $full[0] ) || $short[0] !== $full[0] ) {
			return false;
		}
		if ( count( $short ) < 2 ) {
			return true;
		}

		return substr( end( $short ), 0, 1 ) === substr( end( $full ), 0, 1 );
	}

	private function competitor_names_for_player( $player_name ) {
		$target_name = $this->normalize_name( $player_name );
		if ( isset( $this->competitor_name_cache[ $target_name ] ) ) {
			return $this->competitor_name_cache[ $target_name ];
		}

		$names = array( sanitize_text_field( $player_name ) );
		foreach ( $this->pair_roster() as $entry ) {
			$registered_name = ! empty( $entry['name'] ) ? (string) $entry['name'] : '';
			if ( '' === $registered_name && ! empty( $entry['player']['name'] ) ) {
				$registered_name = (string) $entry['player']['name'];
			}
			if ( $target_name !== $this->normalize_name( $registered_name ) || empty( $entry['team']['name'] ) ) {
				continue;
			}
			$names[] = sanitize_text_field( $entry['team']['name'] );
		}

		$names = array_values( array_unique( array_filter( $names ) ) );
		$this->competitor_name_cache[ $target_name ] = $names;
		return $names;
	}

	private function pair_roster() {
		if ( is_array( $this->pair_roster ) ) {
			return $this->pair_roster;
		}

		$cache_key = 'typlive_dp_pair_roster_' . md5( implode( '|', self::PAIR_TOURNAMENT_IDS ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$this->pair_roster = $cached;
			return $cached;
		}

		$query = <<<'GRAPHQL'
query PairRoster($tournament_ids: [Int!]!) {
  tournament_players(
    where: {
      tournament_id: { _in: $tournament_ids }
      team_id: { _is_null: false }
    }
  ) {
    name
    player { name }
    team { name }
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
						'variables' => array( 'tournament_ids' => self::PAIR_TOURNAMENT_IDS ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->pair_roster = array();
			return $this->pair_roster;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->pair_roster = isset( $data['data']['tournament_players'] ) && is_array( $data['data']['tournament_players'] ) ? $data['data']['tournament_players'] : array();
		set_transient( $cache_key, $this->pair_roster, 15 );
		return $this->pair_roster;
	}

	private function normalize_name( $name ) {
		$name = remove_accents( strtolower( trim( (string) $name ) ) );
		$name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
		return trim( preg_replace( '/\s+/', ' ', $name ) );
	}
}
