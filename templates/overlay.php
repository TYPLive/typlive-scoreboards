<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<meta http-equiv="Cache-Control" content="no-store">
	<title><?php echo esc_html( $data['player']['name'] . ' — Live Scoreboard' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/scoreboard.css?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>">
</head>
<body class="typlive-overlay-page">
	<?php include TYPLIVE_SCOREBOARDS_DIR . 'templates/scoreboard.php'; ?>
	<script src="<?php echo esc_url( TYPLIVE_SCOREBOARDS_URL . 'assets/scoreboard.js?ver=' . TYPLIVE_SCOREBOARDS_VERSION ); ?>" defer></script>
</body>
</html>

