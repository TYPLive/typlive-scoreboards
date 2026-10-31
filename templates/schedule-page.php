<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : array();
$players  = isset( $data['players'] ) && is_array( $data['players'] ) ? $data['players'] : array();
$scoreboards = isset( $data['scoreboards'] ) && is_array( $data['scoreboards'] ) ? $data['scoreboards'] : array();
$source = isset( $data['source'] ) && is_array( $data['source'] ) ? $data['source'] : array();
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
	<title>Spots &amp; Stripes on Tour schedule</title>
	<link rel="stylesheet" href="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/schedule-page.css?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>">
</head>
<body class="typlive-public-schedule">
	<main
		class="upusa-schedule"
		data-schedule-app
		data-endpoint="<?php echo esc_url( $config['endpoint'] ); ?>"
		data-refresh="<?php echo esc_attr( $config['refreshSeconds'] ); ?>"
		data-calendar-base="<?php echo esc_url( home_url( '/upusa-calendar' ) ); ?>"
	>
		<header class="upusa-schedule-hero">
			<div>
				<p class="upusa-eyebrow">2026 Ultimate Pool USA</p>
				<h1>Spots &amp; Stripes on Tour</h1>
				<p class="upusa-intro">Follow all the players from Spots &amp; Stripes live at the 2026 Ultimate Pool Grand Finals in Harrah's Cherokee Casino Resort, North Carolina, USA</p>
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
		<section class="upusa-schedule-tools" aria-label="Schedule options">
			<div class="upusa-data-source<?php echo empty( $source['ok'] ) ? ' has-warning' : ''; ?>" data-source-status data-source-ok="<?php echo ! empty( $source['ok'] ) ? '1' : '0'; ?>" data-source-last-success="<?php echo esc_attr( ! empty( $source['last_success_at'] ) ? $source['last_success_at'] : '' ); ?>">
				<span></span><div><strong>DigitalPool data</strong><small data-source-copy><?php echo ! empty( $source['last_success_at'] ) ? 'Last successful update recorded' : 'Waiting for first successful update'; ?></small></div>
			</div>
			<div class="upusa-calendar-subscribe"><div class="upusa-timezone-note"><strong>Event + UK time</strong><small>Both shown together for every dated match</small></div><button type="button" class="upusa-calendar-button" data-subscribe-all>Subscribe for automatic updates</button></div>
		</section>
		<nav class="upusa-scoreboard-links" aria-label="Open player scoreboards">
			<span>Scoreboard quick links</span>
			<div>
				<?php foreach ( $scoreboards as $scoreboard ) : ?>
					<?php $scoreboard_url = home_url( '/scoreboard-' . $scoreboard['slug'] . '/' ); ?>
					<span class="upusa-scoreboard-link"><a href="<?php echo esc_url( $scoreboard_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $scoreboard['name'] ); ?><span aria-hidden="true">↗</span></a><button type="button" data-share-scoreboard data-url="<?php echo esc_attr( $scoreboard_url ); ?>" data-name="<?php echo esc_attr( $scoreboard['name'] ); ?>" aria-label="Share <?php echo esc_attr( $scoreboard['name'] ); ?> scoreboard">Share</button></span>
				<?php endforeach; ?>
			</div>
		</nav>

		<section class="upusa-next-match" data-next-match hidden>
			<div class="upusa-next-label"><span>Next match</span><strong data-next-countdown>Calculating…</strong></div>
			<div class="upusa-next-copy"><strong data-next-player></strong><span data-next-opponent></span></div>
			<div class="upusa-next-meta"><span data-next-time></span><span data-next-table></span></div>
			<button type="button" class="upusa-calendar-button" data-next-calendar>Add to calendar</button>
		</section>

		<div class="upusa-schedule-sections">
			<?php foreach ( $sections as $state => $section ) : ?>
				<section class="upusa-section upusa-section-<?php echo esc_attr( $state ); ?>" data-schedule-section="<?php echo esc_attr( $state ); ?>"<?php echo 'live' === $state && empty( $section['matches'] ) ? ' hidden' : ''; ?>>
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
								<article class="upusa-match<?php echo 'live' === $state ? ' is-live' : ''; ?>" data-player="<?php echo esc_attr( $match['player_slug'] ); ?>" data-match-state="<?php echo esc_attr( $state ); ?>" data-scheduled-time="<?php echo esc_attr( $match['scheduled_time'] ); ?>" data-player-name="<?php echo esc_attr( $match['tracked_player'] ); ?>" data-opponent-name="<?php echo esc_attr( $match['opponent_name'] ); ?>" data-tournament="<?php echo esc_attr( $match['tournament'] ); ?>" data-round="<?php echo esc_attr( $match['round'] ); ?>" data-table="<?php echo esc_attr( $match['table'] ); ?>">
									<div class="upusa-match-time">
										<time data-match-time<?php echo ! empty( $match['scheduled_time'] ) ? ' datetime="' . esc_attr( $match['scheduled_time'] ) . '"' : ''; ?>><?php echo esc_html( ! empty( $match['scheduled_label'] ) ? $match['scheduled_label'] : 'Date & time TBC' ); ?></time>
										<?php if ( 'live' === $state ) : ?><span>Live</span><?php endif; ?>
									</div>
									<div class="upusa-match-player"><strong><?php echo esc_html( $match['tracked_player'] ); ?></strong></div>
									<div class="upusa-match-detail">
										<div class="upusa-match-copy"><strong>vs <?php echo esc_html( $match['opponent_name'] ); ?></strong><span><?php echo esc_html( $match['tournament'] . ' · ' . $match['round'] ); ?></span></div>
										<?php if ( $show_score ) : ?><div class="upusa-inline-score is-<?php echo esc_attr( $outcome_class ); ?>"><strong><?php echo esc_html( $match['player_score'] . ' – ' . $match['opponent_score'] ); ?></strong><small><?php echo esc_html( 'live' === $state ? 'Current' : $outcome ); ?></small></div><?php endif; ?>
									</div>
									<div class="upusa-match-actions"><span class="upusa-match-table"><?php echo esc_html( $match['table'] ); ?></span><?php if ( 'upcoming' === $state && ! empty( $match['scheduled_time'] ) ) : ?><button type="button" class="upusa-calendar-button" data-add-calendar>Add to calendar</button><?php endif; ?></div>
								</article>
							<?php endforeach; ?>
						</div>
						<p class="upusa-filter-empty" data-section-empty<?php echo 'live' === $state || ! empty( $section['matches'] ) ? ' hidden' : ''; ?>><?php echo esc_html( $section['empty'] ); ?></p>
					</div>
				</section>
			<?php endforeach; ?>
		</div>

		<footer class="upusa-schedule-footer">
			<span>Match times show event-local Eastern Time and UK time</span>
			<a href="https://digitalpool.com/events/2026-ultimate-pool-usa-league-grand-finals/player-schedules" target="_blank" rel="noopener">Official event schedule</a>
		</footer>
		<div class="upusa-toast" data-toast role="status" aria-live="polite" hidden></div>
	</main>
	<script src="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/schedule-page.js?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>" defer></script>
</body>
</html>
