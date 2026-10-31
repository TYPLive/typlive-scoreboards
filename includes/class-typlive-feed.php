<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TYPLive_Feed {
	/** @var array */
	private $settings;

	public function __construct( $settings ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	public function score_for( $board ) {
		$mode = isset( $this->settings['mode'] ) ? $this->settings['mode'] : 'demo';
		if ( 'live' !== $mode ) {
			return $this->manual_score( $board, true );
		}

		$schedule = $this->fetch_digitalpool_match( $board );
		if ( ! empty( $board['event_id'] ) && ! empty( $board['player_id'] ) ) {
			$live     = $this->fetch_agoneus_score( $board );
			if ( ! is_wp_error( $live ) ) {
				return is_array( $schedule ) ? $this->apply_digitalpool_match( $live, $schedule, $board ) : $live;
			}

			if ( is_array( $schedule ) ) {
				$fallback                 = $this->apply_digitalpool_match( $this->waiting_score( $board ), $schedule, $board );
				$fallback['feed_message'] = $live->get_error_message();
				return $fallback;
			}

			$fallback                 = $this->waiting_score( $board );
			$fallback['feed_ok']      = false;
			$fallback['feed_message'] = $live->get_error_message();
			return $fallback;
		}

		if ( is_array( $schedule ) ) {
			return $this->apply_digitalpool_match( $this->waiting_score( $board ), $schedule, $board );
		}

		$slug      = isset( $board['slug'] ) ? sanitize_title( $board['slug'] ) : 'scoreboard';
		$cache_key = 'typlive_score_' . md5( $slug . '|' . (string) $this->settings['source_url'] );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$live = $this->fetch_live_score( $board );
		if ( is_wp_error( $live ) ) {
			$fallback                 = $this->manual_score( $board, false );
			$fallback['status']       = 'Feed waiting';
			$fallback['feed_ok']      = false;
			$fallback['feed_message'] = $live->get_error_message();
			$live                     = $fallback;
		}

		$ttl = max( 3, min( 60, absint( $this->settings['refresh_seconds'] ) ) );
		set_transient( $cache_key, $live, $ttl );

		return $live;
	}

	private function fetch_digitalpool_match( $board ) {
		if ( empty( $board['player_name'] ) ) {
			return null;
		}

		$api = new TYPLive_DigitalPool();
		return $api->match_for_player( $board['player_name'] );
	}

	private function apply_digitalpool_match( $score, $schedule, $board ) {
		if ( ! is_array( $score ) || ! is_array( $schedule ) ) {
			return $score;
		}

		$score['round']          = ! empty( $schedule['round'] ) ? (string) $schedule['round'] : $score['round'];
		$score['table']          = ! empty( $schedule['table'] ) ? (string) $schedule['table'] : $score['table'];
		$score['scheduled_time'] = isset( $schedule['scheduled_time'] ) ? (string) $schedule['scheduled_time'] : '';
		$score['match_number']   = isset( $schedule['match_number'] ) ? absint( $schedule['match_number'] ) : 0;
		$score['tournament']     = isset( $schedule['tournament'] ) ? (string) $schedule['tournament'] : '';
		if ( ! empty( $schedule['is_pair'] ) && ( empty( $score['live'] ) || ! empty( $schedule['live'] ) ) ) {
			$score['player']['name']   = ! empty( $schedule['player_name'] ) ? (string) $schedule['player_name'] : $score['player']['name'];
			$score['opponent']['name'] = ! empty( $schedule['opponent_name'] ) ? (string) $schedule['opponent_name'] : $score['opponent']['name'];
		}

		if ( empty( $score['live'] ) ) {
			$score['live']              = ! empty( $schedule['live'] );
			$score['feed_ok']           = true;
			$score['status']            = isset( $schedule['status'] ) ? ucwords( strtolower( str_replace( '_', ' ', $schedule['status'] ) ) ) : 'Scheduled';
			$score['player']['name']    = ! empty( $schedule['is_pair'] ) && ! empty( $schedule['player_name'] ) ? (string) $schedule['player_name'] : ( isset( $board['player_name'] ) ? (string) $board['player_name'] : (string) $schedule['player_name'] );
			$score['player']['score']   = isset( $schedule['player_score'] ) ? absint( $schedule['player_score'] ) : 0;
			$score['opponent']['name']  = ! empty( $schedule['opponent_name'] ) ? (string) $schedule['opponent_name'] : 'Opponent TBC';
			$score['opponent']['score'] = isset( $schedule['opponent_score'] ) ? absint( $schedule['opponent_score'] ) : 0;
			$score['source']            = 'digitalpool';
		}

		return $score;
	}

	private function manual_score( $board, $is_demo ) {
		return array(
			'event'           => isset( $this->settings['event_name'] ) ? (string) $this->settings['event_name'] : 'Spots & Stripes on Tour',
			'round'           => isset( $board['round_name'] ) ? (string) $board['round_name'] : ( $is_demo ? 'Last 32' : 'Match' ),
			'live'            => true,
			'is_demo'         => (bool) $is_demo,
			'feed_ok'         => true,
			'feed_message'    => '',
			'status'          => $is_demo ? 'Design preview' : 'Last configured score',
			'table'           => isset( $board['table'] ) ? (string) $board['table'] : '',
			'race_to'         => isset( $board['race_to'] ) ? absint( $board['race_to'] ) : 0,
			'player'          => array(
				'name'  => isset( $board['player_name'] ) ? (string) $board['player_name'] : 'Player',
				'score' => isset( $board['player_score'] ) ? absint( $board['player_score'] ) : 0,
			),
			'opponent'        => array(
				'name'  => isset( $board['opponent_name'] ) ? (string) $board['opponent_name'] : 'Opponent',
				'score' => isset( $board['opponent_score'] ) ? absint( $board['opponent_score'] ) : 0,
			),
			'updated_at'      => gmdate( 'c' ),
			'source'          => 'wordpress',
		);
	}

	private function waiting_score( $board ) {
		return array(
			'event'        => isset( $board['event_name'] ) ? (string) $board['event_name'] : ( isset( $this->settings['event_name'] ) ? (string) $this->settings['event_name'] : 'Spots & Stripes on Tour' ),
			'round'        => 'Waiting for match',
			'live'         => false,
			'is_demo'      => false,
			'feed_ok'      => true,
			'feed_message' => '',
			'status'       => 'Waiting for live match',
			'table'        => 'Table TBC',
			'race_to'      => 0,
			'player'       => array(
				'name'  => isset( $board['player_name'] ) ? (string) $board['player_name'] : 'Player',
				'score' => '–',
			),
			'opponent'     => array(
				'name'  => 'Opponent TBC',
				'score' => '–',
			),
			'updated_at'   => gmdate( 'c' ),
			'source'       => 'agoneus',
		);
	}

	private function fetch_agoneus_score( $board ) {
		$api     = new TYPLive_Agoneus();
		$matches = $api->live_matches( absint( $board['event_id'] ) );
		if ( is_wp_error( $matches ) ) {
			return $matches;
		}

		if ( isset( $matches['message'] ) ) {
			return $this->waiting_score( $board );
		}

		$player_id = absint( $board['player_id'] );
		$match     = null;
		$side      = 0;
		foreach ( $matches as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			if ( $player_id === absint( isset( $candidate['t1j1'] ) ? $candidate['t1j1'] : 0 ) || $player_id === absint( isset( $candidate['t1j2'] ) ? $candidate['t1j2'] : 0 ) ) {
				$match = $candidate;
				$side  = 1;
				break;
			}

			if ( $player_id === absint( isset( $candidate['t2j1'] ) ? $candidate['t2j1'] : 0 ) || $player_id === absint( isset( $candidate['t2j2'] ) ? $candidate['t2j2'] : 0 ) ) {
				$match = $candidate;
				$side  = 2;
				break;
			}
		}

		if ( ! $match ) {
			return $this->waiting_score( $board );
		}

		$score1 = isset( $match['s1'] ) ? absint( $match['s1'] ) : 0;
		$score2 = isset( $match['s2'] ) ? absint( $match['s2'] ) : 0;
		if ( ! empty( $match['sets'] ) && is_array( $match['sets'] ) ) {
			$set = end( $match['sets'] );
			if ( is_array( $set ) ) {
				$score1 = isset( $set['sc1'] ) ? absint( $set['sc1'] ) : $score1;
				$score2 = isset( $set['sc2'] ) ? absint( $set['sc2'] ) : $score2;
			}
		}

		$race_to   = 0;
		$round_name = 'Match';
		if ( ! empty( $match['c'] ) ) {
			$category = $api->category( absint( $match['c'] ) );
			if ( ! is_wp_error( $category ) && ! empty( $category['phases'] ) ) {
				$match_phase = absint( isset( $match['p'] ) ? $match['p'] : 0 );
				foreach ( $category['phases'] as $phase_index => $phase ) {
					$phase_code = absint( isset( $phase['code'] ) ? $phase['code'] : $phase_index );
					if ( $phase_code === $match_phase || absint( $phase_index ) === $match_phase ) {
						if ( ! empty( $phase['boFrames'] ) ) {
							$race_to = (int) floor( absint( $phase['boFrames'] ) / 2 ) + 1;
						}
						if ( ! empty( $phase['lib'] ) ) {
							$round_name = sanitize_text_field( $phase['lib'] );
						} elseif ( ! empty( $phase['name'] ) ) {
							$round_name = sanitize_text_field( $phase['name'] );
						}
						break;
					}
				}
			}
		}

		$side1 = $this->agoneus_side_name( $match, 1 );
		$side2 = $this->agoneus_side_name( $match, 2 );

		return array(
			'event'        => isset( $board['event_name'] ) ? (string) $board['event_name'] : 'Spots & Stripes on Tour',
			'round'        => $round_name,
			'live'         => true,
			'is_demo'      => false,
			'feed_ok'      => true,
			'feed_message' => '',
			'status'       => 'Live now',
			'table'        => isset( $match['tn'] ) ? (string) $match['tn'] : 'Table TBC',
			'race_to'      => $race_to,
			'player'       => array(
				'name'  => isset( $board['player_name'] ) ? (string) $board['player_name'] : ( 1 === $side ? $side1 : $side2 ),
				'score' => 1 === $side ? $score1 : $score2,
			),
			'opponent'     => array(
				'name'  => 1 === $side ? $side2 : $side1,
				'score' => 1 === $side ? $score2 : $score1,
			),
			'updated_at'   => gmdate( 'c' ),
			'source'       => 'agoneus',
		);
	}

	private function agoneus_side_name( $match, $side ) {
		$names = array();
		foreach ( array( 1, 2 ) as $position ) {
			$first_key = 't' . $side . 'p' . $position;
			$last_key  = 't' . $side . 'n' . $position;
			$first     = isset( $match[ $first_key ] ) ? trim( (string) $match[ $first_key ] ) : '';
			$last      = isset( $match[ $last_key ] ) ? trim( (string) $match[ $last_key ] ) : '';
			$name      = trim( $first . ' ' . ucwords( strtolower( $last ) ) );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return $names ? implode( ' / ', $names ) : 'Opponent';
	}

	private function fetch_live_score( $board ) {
		$url = isset( $this->settings['source_url'] ) ? esc_url_raw( $this->settings['source_url'] ) : '';
		if ( '' === $url ) {
			return new WP_Error( 'typlive_missing_source', 'No live-scoring source URL is configured.' );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 4,
				'headers'     => array(
					'Accept'     => 'application/json,text/html;q=0.9,*/*;q=0.8',
					'User-Agent' => 'TYP-Live-Scoreboards/' . TYPLIVE_SCOREBOARDS_VERSION . ' (WordPress)',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'typlive_source_http', 'The scoring source returned HTTP ' . absint( $status ) . '.' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'typlive_empty_source', 'The scoring source returned an empty page.' );
		}

		$target = isset( $board['player_name'] ) ? trim( (string) $board['player_name'] ) : '';
		$match  = $this->parse_source( $body, $target );

		if ( ! $match ) {
			return new WP_Error( 'typlive_match_not_found', 'No current match was found for ' . $target . '.' );
		}

		return $this->format_live_match( $match, $target, $board );
	}

	private function parse_source( $body, $target ) {
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			$match = $this->find_json_match( $decoded, $target );
			if ( $match ) {
				return $match;
			}
		}

		if ( class_exists( 'DOMDocument' ) ) {
			$previous = libxml_use_internal_errors( true );
			$dom      = new DOMDocument();
			$loaded   = $dom->loadHTML( $body );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );

			if ( $loaded ) {
				$scripts = $dom->getElementsByTagName( 'script' );
				foreach ( $scripts as $script ) {
					$text = trim( $script->textContent );
					if ( '' === $text || false === stripos( $text, $target ) ) {
						continue;
					}

					$json = json_decode( $text, true );
					if ( is_array( $json ) ) {
						$match = $this->find_json_match( $json, $target );
						if ( $match ) {
							return $match;
						}
					}
				}

				$match = $this->find_html_match( $dom, $target );
				if ( $match ) {
					return $match;
				}
			}
		}

		return null;
	}

	private function find_json_match( $node, $target ) {
		if ( ! is_array( $node ) ) {
			return null;
		}

		$candidate = $this->json_candidate( $node, $target );
		if ( $candidate ) {
			return $candidate;
		}

		foreach ( $node as $child ) {
			if ( is_array( $child ) ) {
				$match = $this->find_json_match( $child, $target );
				if ( $match ) {
					return $match;
				}
			}
		}

		return null;
	}

	private function json_candidate( $node, $target ) {
		$flat = $this->flatten_scalars( $node );
		if ( count( $flat ) < 4 ) {
			return null;
		}

		$haystack = implode( ' ', array_values( $flat ) );
		if ( false === stripos( $haystack, $target ) ) {
			return null;
		}

		$p1 = $this->value_for_keys( $flat, array( 'player1', 'player_1', 'home_player', 'home.name', 'playera', 'player_a', 'competitor1', 'participant1', 'left.name' ) );
		$p2 = $this->value_for_keys( $flat, array( 'player2', 'player_2', 'away_player', 'away.name', 'playerb', 'player_b', 'competitor2', 'participant2', 'right.name' ) );
		$s1 = $this->value_for_keys( $flat, array( 'score1', 'score_1', 'home_score', 'home.score', 'player1_score', 'playera_score', 'left.score' ) );
		$s2 = $this->value_for_keys( $flat, array( 'score2', 'score_2', 'away_score', 'away.score', 'player2_score', 'playerb_score', 'right.score' ) );

		if ( null === $p1 || null === $p2 || null === $s1 || null === $s2 ) {
			return null;
		}

		return array(
			'player1' => (string) $p1,
			'player2' => (string) $p2,
			'score1'  => absint( $s1 ),
			'score2'  => absint( $s2 ),
			'race_to' => absint( $this->value_for_keys( $flat, array( 'race_to', 'raceto', 'target_score', 'race' ) ) ),
			'table'   => (string) $this->value_for_keys( $flat, array( 'table_name', 'tablename', 'table', 'court', 'venue_area' ) ),
			'status'  => (string) $this->value_for_keys( $flat, array( 'status', 'match_status', 'state', 'phase' ) ),
		);
	}

	private function flatten_scalars( $node, $prefix = '', $depth = 0 ) {
		$out = array();
		if ( $depth > 3 || ! is_array( $node ) ) {
			return $out;
		}

		foreach ( $node as $key => $value ) {
			$key  = strtolower( preg_replace( '/[^a-z0-9_]+/i', '_', (string) $key ) );
			$path = '' === $prefix ? $key : $prefix . '.' . $key;
			if ( is_scalar( $value ) || null === $value ) {
				$out[ $path ] = (string) $value;
				$out[ $key ]  = (string) $value;
			} elseif ( is_array( $value ) ) {
				$out = array_merge( $out, $this->flatten_scalars( $value, $path, $depth + 1 ) );
			}
		}

		return $out;
	}

	private function value_for_keys( $flat, $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $flat ) && '' !== trim( (string) $flat[ $key ] ) ) {
				return $flat[ $key ];
			}

			foreach ( $flat as $path => $value ) {
				if ( substr( $path, -strlen( $key ) ) === $key && '' !== trim( (string) $value ) ) {
					return $value;
				}
			}
		}

		return null;
	}

	private function find_html_match( DOMDocument $dom, $target ) {
		$xpath = new DOMXPath( $dom );
		$lower = strtolower( $target );
		$query = "//tr[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '" . $this->xpath_literal( $lower ) . "')]";
		$rows = $xpath->query( $query );

		if ( $rows && $rows->length ) {
			$text = trim( preg_replace( '/\s+/', ' ', $rows->item( 0 )->textContent ) );
			return $this->parse_html_text( $text, $target );
		}

		return null;
	}

	private function parse_html_text( $text, $target ) {
		if ( ! preg_match_all( '/\b\d{1,2}\b/', $text, $numbers ) || count( $numbers[0] ) < 2 ) {
			return null;
		}

		$score1 = absint( $numbers[0][ count( $numbers[0] ) - 2 ] );
		$score2 = absint( $numbers[0][ count( $numbers[0] ) - 1 ] );
		$names  = preg_split( '/\s+\d{1,2}\s+|\s+(?:vs\.?|v)\s+/i', $text );
		$names  = array_values( array_filter( array_map( 'trim', $names ) ) );

		if ( count( $names ) < 2 ) {
			return null;
		}

		return array(
			'player1' => $names[0],
			'player2' => $names[1],
			'score1'  => $score1,
			'score2'  => $score2,
			'race_to' => 0,
			'table'   => '',
			'status'  => 'Live',
		);
	}

	private function format_live_match( $match, $target, $board ) {
		$target_is_first = false !== stripos( $match['player1'], $target );
		$player_name     = $target_is_first ? $match['player1'] : $match['player2'];
		$opponent_name   = $target_is_first ? $match['player2'] : $match['player1'];
		$player_score    = $target_is_first ? $match['score1'] : $match['score2'];
		$opponent_score  = $target_is_first ? $match['score2'] : $match['score1'];

		return array(
			'event'        => isset( $this->settings['event_name'] ) ? (string) $this->settings['event_name'] : 'Spots & Stripes on Tour',
			'round'        => ! empty( $match['status'] ) ? (string) $match['status'] : 'Match',
			'live'         => true,
			'is_demo'      => false,
			'feed_ok'      => true,
			'feed_message' => '',
			'status'       => ! empty( $match['status'] ) ? (string) $match['status'] : 'Live',
			'table'        => ! empty( $match['table'] ) ? (string) $match['table'] : ( isset( $board['table'] ) ? (string) $board['table'] : '' ),
			'race_to'      => ! empty( $match['race_to'] ) ? absint( $match['race_to'] ) : ( isset( $board['race_to'] ) ? absint( $board['race_to'] ) : 0 ),
			'player'       => array( 'name' => $player_name, 'score' => absint( $player_score ) ),
			'opponent'     => array( 'name' => $opponent_name, 'score' => absint( $opponent_score ) ),
			'updated_at'   => gmdate( 'c' ),
			'source'       => 'live',
		);
	}

	private function xpath_literal( $value ) {
		return str_replace( "'", '', $value );
	}
}

