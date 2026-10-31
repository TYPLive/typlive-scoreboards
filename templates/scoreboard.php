<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = TYPLive_Scoreboards::settings();
$accent   = isset( $settings['accent'] ) ? sanitize_hex_color( $settings['accent'] ) : '#E32934';
$accent   = $accent ? $accent : '#E32934';
?>
<section
	class="typlive-scoreboard"
	data-typlive-scoreboard
	data-endpoint="<?php echo esc_url( $config['endpoint'] ); ?>"
	data-refresh="<?php echo esc_attr( $config['refreshSeconds'] ); ?>"
	style="--typlive-accent: <?php echo esc_attr( $accent ); ?>;"
	aria-label="Live match score"
>
	<div class="typlive-card">
		<div class="typlive-round" data-field="round"><?php echo esc_html( isset( $data['round'] ) ? $data['round'] : $data['status'] ); ?></div>

		<div class="typlive-match">
			<div class="typlive-player typlive-player-featured" data-player="player">
				<span class="typlive-turn" aria-label="Tracked player"></span>
				<span class="typlive-name" data-field="player-name"><?php echo esc_html( $data['player']['name'] ); ?></span>
				<span class="typlive-score" data-field="player-score"><?php echo esc_html( $data['player']['score'] ); ?></span>
			</div>
			<div class="typlive-player typlive-player-opponent" data-player="opponent">
				<span class="typlive-score" data-field="opponent-score"><?php echo esc_html( $data['opponent']['score'] ); ?></span>
				<span class="typlive-name" data-field="opponent-name"><?php echo esc_html( $data['opponent']['name'] ); ?></span>
			</div>
		</div>
	</div>
</section>

