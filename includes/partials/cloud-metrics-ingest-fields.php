<?php
/**
 * CreatorReactor Cloud — Metrics ingest (edge URL + bearer token).
 *
 * Expected variables: $option_name, $opts.
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$metrics_token_mask = '********';
$metrics_token_val  = ! empty( $opts['creatorreactor_metrics_ingest_token'] ) ? $metrics_token_mask : '';
?>
<div class="creatorreactor-cloud-configuration">
	<div class="creatorreactor-settings-block">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Send operational metrics', 'creatorreactor' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[metrics_ingest_enabled]" value="1" <?php checked( ! empty( $opts['metrics_ingest_enabled'] ) ); ?> />
						<?php esc_html_e( 'Enable sending anonymized events (e.g. scheduled sync completion) to the metrics ingest service.', 'creatorreactor' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="creatorreactor_metrics_ingest_url"><?php esc_html_e( 'Metrics ingest URL', 'creatorreactor' ); ?></label></th>
				<td>
					<input type="url" id="creatorreactor_metrics_ingest_url" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_metrics_ingest_url]" value="<?php echo esc_attr( $opts['creatorreactor_metrics_ingest_url'] ?? '' ); ?>" autocomplete="off" inputmode="url" placeholder="<?php echo esc_attr( 'http://localhost:18081' ); ?>" />
					<p class="description"><?php esc_html_e( 'Base URL of the ingest API (POST /v1/ingest). In Docker/Podman Compose, set CREATORREACTOR_METRICS_INGEST_URL for server-side PHP (see compose.yaml) or use http://localhost:18081 from the host.', 'creatorreactor' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="creatorreactor_metrics_ingest_token"><?php esc_html_e( 'Metrics ingest token', 'creatorreactor' ); ?></label></th>
				<td>
					<input type="password" id="creatorreactor_metrics_ingest_token" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_metrics_ingest_token]" value="<?php echo esc_attr( $metrics_token_val ); ?>" autocomplete="off" placeholder="<?php echo esc_attr( __( 'Bearer token (leave masked to keep)', 'creatorreactor' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Bearer token for Authorization: Bearer. Stored encrypted; leave the masked value to keep the existing token. Optional if CREATORREACTOR_METRICS_INGEST_TOKEN is set in the environment.', 'creatorreactor' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>
