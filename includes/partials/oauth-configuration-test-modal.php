<?php
/**
 * Shared “configuration check” modal shell (OAuth / OFAuth test flows).
 *
 * @package CreatorReactor
 *
 * @var array $cr_oauth_test_modal {
 *   @type string $modal_id       Root element id.
 *   @type string $title_id       Title element id (aria-labelledby).
 *   @type string $title_text     Modal heading (plain text).
 *   @type string $class_prefix   Prefix for -dismiss, -status, -remediation-wrap, -remediation, -footer-dismiss, -acknowledge.
 *   @type string $backdrop_extra_class Optional extra class on backdrop.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$m = isset( $cr_oauth_test_modal ) && is_array( $cr_oauth_test_modal ) ? $cr_oauth_test_modal : [];
if ( empty( $m['modal_id'] ) || empty( $m['title_id'] ) || empty( $m['class_prefix'] ) ) {
	return;
}
$title_text            = isset( $m['title_text'] ) ? (string) $m['title_text'] : '';
$backdrop_extra        = isset( $m['backdrop_extra_class'] ) ? trim( (string) $m['backdrop_extra_class'] ) : '';
$backdrop_class        = trim( 'creatorreactor-modal-backdrop ' . $backdrop_extra );
$class_prefix          = (string) $m['class_prefix'];
$status_class          = $class_prefix . '-status';
$remediation_wrap_id   = $class_prefix . '-remediation-wrap';
$remediation_id        = $class_prefix . '-remediation';
?>
<div id="<?php echo esc_attr( (string) $m['modal_id'] ); ?>" class="creatorreactor-modal" aria-hidden="true" role="presentation">
	<div class="<?php echo esc_attr( $backdrop_class ); ?>" aria-hidden="true"></div>
	<div class="creatorreactor-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( (string) $m['title_id'] ); ?>">
		<div class="creatorreactor-modal-header">
			<div class="creatorreactor-modal-header-title">
				<h3 id="<?php echo esc_attr( (string) $m['title_id'] ); ?>"><?php echo esc_html( $title_text ); ?></h3>
			</div>
			<button type="button" class="<?php echo esc_attr( $class_prefix ); ?>-dismiss" aria-label="<?php esc_attr_e( 'Close', 'wp-creatorreactor' ); ?>">&times;</button>
		</div>
		<div class="creatorreactor-modal-body">
			<p id="<?php echo esc_attr( $class_prefix ); ?>-status" class="<?php echo esc_attr( $status_class ); ?>"></p>
			<div id="<?php echo esc_attr( $remediation_wrap_id ); ?>" class="<?php echo esc_attr( $class_prefix ); ?>-remediation-wrap" hidden>
				<strong><?php esc_html_e( 'What to do next', 'wp-creatorreactor' ); ?></strong>
				<p id="<?php echo esc_attr( $remediation_id ); ?>" class="<?php echo esc_attr( $class_prefix ); ?>-remediation"></p>
			</div>
		</div>
		<div class="creatorreactor-modal-footer">
			<button type="button" class="button <?php echo esc_attr( $class_prefix ); ?>-footer-dismiss" hidden><?php esc_html_e( 'Close', 'wp-creatorreactor' ); ?></button>
			<button type="button" class="button button-primary <?php echo esc_attr( $class_prefix ); ?>-acknowledge" hidden><?php esc_html_e( 'Acknowledge', 'wp-creatorreactor' ); ?></button>
		</div>
	</div>
</div>
