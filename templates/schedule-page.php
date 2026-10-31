<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
$players  = isset( $data['players'] ) && is_array( $data['players'] ) ? $data['players'] : array();
$sections = array(
	'live'     => array( 'title' => 'Live now', 'empty' => 'No live matches right now.', 'matches' => array() ),
	'upcoming' => array( 'title' => 'Upcoming matches', 'empty' => 'No upcoming matches are published for this player.', 'matches' => array() ),
	'result'   => array( 'title' => 'Results', 'empty' => 'No results are published for this player yet.', 'matches' => array() ),
);
foreach ( $schedule as $match ) {
	$state = isset( $match['state'] ) && isset( $sections[ $match['state'] ] ) ? $match['state'] : 'upcoming';
	$sections[ $state ]['matches'][] = $match;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<meta http-equiv="Cache-Control" content="no-store">
	<title>UPUSA tracked player schedule</title>
	<link rel="stylesheet" href="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/schedule-page.css?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>">
</head>
<body class="typlive-public-schedule">
	<main
		class="upusa-schedule"
		data-schedule-app
		data-endpoint="<?php echo esc_url( $config['endpoint'] ); ?>"
		data-refresh="<?php echo esc_attr( $config['refreshSeconds'] ); ?>"
	>
		<header class="upusa-schedule-hero">
			<div>
				<p class="upusa-eyebrow">Ultimate Pool USA</p>
				<h1>Tracked player schedule</h1>
				<p class="upusa-intro">Match times, opponents and table allocations for the players we are following at the 2026 League Grand Finals.</p>
			</div>
			<div class="upusa-live-state" data-feed-state>
				<span></span>
				<div><strong>Live schedule</strong><small data-updated>Updates automatically</small></div>
			</div>
		</header>

		<nav class="upusa-player-filters" aria-label="Filter schedule by player" data-player-filters>
			<button type="button" data-filter="all" aria-pressed="true">All players</button>
			<?php foreach ( $players as $player ) : ?>
				<button type="button" data-filter="<?php echo esc_attr( $player['slug'] ); ?>" aria-pressed="false"><?php echo esc_html( $player['name'] ); ?></button>
			<?php endforeach; ?>
		</nav>
		<label class="upusa-player-select">
			<span>Show player</span>
			<select data-player-select>
				<option value="all">All players</option>
				<?php foreach ( $players as $player ) : ?>
					<option value="<?php echo esc_attr( $player['slug'] ); ?>"><?php echo esc_html( $player['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<div class="upusa-schedule-sections">
			<?php foreach ( $sections as $state => $section ) : ?>
				<section class="upusa-section upusa-section-<?php echo esc_attr( $state ); ?>" data-schedule-section="<?php echo esc_attr( $state ); ?>">
					<header class="upusa-section-header"><h2><?php echo esc_html( $section['title'] ); ?></h2><span data-section-count><?php echo esc_html( count( $section['matches'] ) ); ?></span></header>
					<div class="upusa-schedule-panel">
						<div class="upusa-schedule-columns" aria-hidden="true">
							<span>Date &amp; time</span><span>Player</span><span>Match</span><span>Table</span>
						</div>
						<div class="upusa-schedule-rows" data-schedule-rows="<?php echo esc_attr( $state ); ?>">
							<?php foreach ( $section['matches'] as $match ) : ?>
								<?php
								$outcome       = ! empty( $match['result_outcome'] ) ? $match['result_outcome'] : 'Final';
								$outcome_class = sanitize_html_class( strtolower( $outcome ) );
								$show_score    = 'live' === $state || ( 'result' === $state && ! empty( $match['score_available'] ) );
								?>
								<article class="upusa-match<?php echo 'live' === $state ? ' is-live' : ''; ?>" data-player="<?php echo esc_attr( $match['player_slug'] ); ?>" data-match-state="<?php echo esc_attr( $state ); ?>">
									<div class="upusa-match-time">
										<time<?php echo ! empty( $match['scheduled_time'] ) ? ' datetime="' . esc_attr( $match['scheduled_time'] ) . '"' : ''; ?>><?php echo esc_html( ! empty( $match['scheduled_label'] ) ? $match['scheduled_label'] : 'Date & time TBC' ); ?></time>
										<?php if ( 'live' === $state ) : ?><span>Live</span><?php endif; ?>
									</div>
									<div class="upusa-match-player"><strong><?php echo esc_html( $match['tracked_player'] ); ?></strong></div>
									<div class="upusa-match-detail">
										<div class="upusa-match-copy"><strong>vs <?php echo esc_html( $match['opponent_name'] ); ?></strong><span><?php echo esc_html( $match['tournament'] . ' · ' . $match['round'] ); ?></span></div>
										<?php if ( $show_score ) : ?><div class="upusa-inline-score is-<?php echo esc_attr( $outcome_class ); ?>"><strong><?php echo esc_html( $match['player_score'] . ' – ' . $match['opponent_score'] ); ?></strong><small><?php echo esc_html( 'live' === $state ? 'Current' : $outcome ); ?></small></div><?php endif; ?>
									</div>
									<span class="upusa-match-table"><?php echo esc_html( $match['table'] ); ?></span>
								</article>
							<?php endforeach; ?>
						</div>
						<p class="upusa-filter-empty" data-section-empty<?php echo empty( $section['matches'] ) ? '' : ' hidden'; ?>><?php echo esc_html( $section['empty'] ); ?></p>
					</div>
				</section>
			<?php endforeach; ?>
		</div>

		<footer class="upusa-schedule-footer">
			<span>Times shown in Eastern Time</span>
			<a href="https://digitalpool.com/events/2026-ultimate-pool-usa-league-grand-finals/player-schedules" target="_blank" rel="noopener">Official event schedule</a>
		</footer>
	</main>
	<script src="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/schedule-page.js?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>" defer></script>
</body>
</html>
