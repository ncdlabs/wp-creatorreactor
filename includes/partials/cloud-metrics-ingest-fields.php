<?php
/**
 * CreatorReactor Cloud — Metrics ingest (read-only URL + bearer token).
 *
 * Expected variables: $option_name, $opts, $metrics_resolved_url.
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$metrics_token_mask = '********';
$metrics_token_val  = ! empty( $opts['creatorreactor_metrics_ingest_token'] ) ? $metrics_token_mask : '';
$display_url        = is_string( $metrics_resolved_url ) ? $metrics_resolved_url : '';
?>
<div class="creatorreactor-cloud-configuration">
	<div class="creatorreactor-settings-block">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="creatorreactor_metrics_ingest_url"><?php esc_html_e( 'Metrics ingest URL', 'wp-creatorreactor' ); ?></label></th>
				<td>
					<input type="url" id="creatorreactor_metrics_ingest_url" class="regular-text code" value="<?php echo esc_attr( $display_url ); ?>" readonly autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Resolved base URL for POST /v1/ingest (scheduled sync completion events). Set CREATORREACTOR_METRICS_INGEST_URL in the server environment, or configure the stored URL at install time; this field is not editable here.', 'wp-creatorreactor' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="creatorreactor_metrics_ingest_token"><?php esc_html_e( 'Metrics ingest token', 'wp-creatorreactor' ); ?></label></th>
				<td>
					<input type="password" id="creatorreactor_metrics_ingest_token" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_metrics_ingest_token]" value="<?php echo esc_attr( $metrics_token_val ); ?>" autocomplete="off" placeholder="<?php echo esc_attr( __( 'Bearer token (leave masked to keep)', 'wp-creatorreactor' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Bearer secret shared with the data-ingestion service (Authorization: Bearer). It is not your CreatorReactor Cloud login—those credentials are for OAuth/Fanvue APIs; this token only authorizes this site’s plugin to the metrics edge. Stored encrypted; leave the masked value to keep the existing token. Optional if CREATORREACTOR_METRICS_INGEST_TOKEN is set in the environment.', 'wp-creatorreactor' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>
