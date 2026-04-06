<?php
/**
 * CreatorReactor Cloud — Schema Service URL (locked card body).
 *
 * Expected variables: $option_name, $opts.
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="creatorreactor-cloud-configuration">
	<div class="creatorreactor-settings-block">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="creatorreactor_schema_service_url"><?php esc_html_e( 'Schema Service URL', 'wp-creatorreactor' ); ?></label></th>
				<td>
					<input type="url" id="creatorreactor_schema_service_url" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_schema_service_url]" value="<?php echo esc_attr( $opts['creatorreactor_schema_service_url'] ?? Admin_Settings::DEFAULT_SCHEMA_SERVICE_URL ); ?>" autocomplete="off" inputmode="url" placeholder="<?php echo esc_attr( Admin_Settings::DEFAULT_SCHEMA_SERVICE_URL ); ?>" />
					<p class="description"><?php esc_html_e( 'Base URL of the Schema Service API (from your browser, local stack is often http://localhost:18080). Server-side PHP in Docker/Podman should use the in-network URL via env CREATORREACTOR_SCHEMA_SERVICE_URL (see compose.yaml) or set that URL here.', 'wp-creatorreactor' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>
