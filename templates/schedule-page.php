<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
$players  = isset( $data['players'] ) && is_array( $data['players'] ) ? $data['players'] : array();
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

		<section class="upusa-schedule-panel" aria-label="Upcoming matches">
			<div class="upusa-schedule-columns" aria-hidden="true">
				<span>Date &amp; time</span><span>Player</span><span>Match</span><span>Table</span>
			</div>
			<div class="upusa-schedule-rows" data-schedule-rows>
				<?php if ( empty( $schedule ) ) : ?>
					<p class="upusa-empty">No published matches yet. This page will update when the schedule is released.</p>
				<?php else : ?>
					<?php foreach ( $schedule as $match ) : ?>
						<article class="upusa-match<?php echo ! empty( $match['live'] ) ? ' is-live' : ''; ?>" data-player="<?php echo esc_attr( $match['player_slug'] ); ?>">
							<div class="upusa-match-time">
								<time<?php echo ! empty( $match['scheduled_time'] ) ? ' datetime="' . esc_attr( $match['scheduled_time'] ) . '"' : ''; ?>><?php echo esc_html( ! empty( $match['scheduled_label'] ) ? $match['scheduled_label'] : 'Date & time TBC' ); ?></time>
								<?php if ( ! empty( $match['live'] ) ) : ?><span>Live</span><?php endif; ?>
							</div>
							<div class="upusa-match-player"><strong><?php echo esc_html( $match['tracked_player'] ); ?></strong></div>
							<div class="upusa-match-detail"><strong>vs <?php echo esc_html( $match['opponent_name'] ); ?></strong><span><?php echo esc_html( $match['tournament'] . ' · ' . $match['round'] ); ?></span></div>
							<span class="upusa-match-table"><?php echo esc_html( $match['table'] ); ?></span>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<p class="upusa-filter-empty" data-filter-empty hidden>No published matches for this player yet.</p>
		</section>

		<footer class="upusa-schedule-footer">
			<span>Times shown in Eastern Time</span>
			<a href="https://digitalpool.com/events/2026-ultimate-pool-usa-league-grand-finals/player-schedules" target="_blank" rel="noopener">Official event schedule</a>
		</footer>
	</main>
	<script src="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/schedule-page.js?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>" defer></script>
</body>
</html>
