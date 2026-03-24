<?php
/**
 * Theme-wrapped onboarding page (rewrite /creatorreactor-onboarding/).
 *
 * @package CreatorReactor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Block themes and some hosts ship without header.php; get_header() then triggers a core deprecation notice.
// We also need to check if the located header.php is the deprecated one in wp-includes/theme-compat.
$header_template = locate_template( 'header.php', false, false );
if ( $header_template && false === strpos( $header_template, '/wp-includes/theme-compat/' ) ) {
	get_header();
} else {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'creatorreactor-onboarding-body' ); ?>>
		<?php
		if ( function_exists( 'wp_body_open' ) ) {
			wp_body_open();
		}
	}
?>
<main id="creatorreactor-onboarding-main" class="creatorreactor-onboarding-main" role="main">
	<?php \CreatorReactor\Onboarding::render_form(); ?>
</main>
<?php
// We also need to check if the located footer.php is the deprecated one in wp-includes/theme-compat.
$footer_template = locate_template( 'footer.php', false, false );
if ( $footer_template && false === strpos( $footer_template, '/wp-includes/theme-compat/' ) ) {
	get_footer();
} else {
	wp_footer();
	?>
</body>
</html>
	<?php
}
