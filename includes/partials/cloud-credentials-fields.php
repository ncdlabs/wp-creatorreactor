<?php
/**
 * CreatorReactor Cloud — account credentials (unlocked card).
 *
 * Expected variables: $option_name, $opts, $cloud_password_mask.
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="creatorreactor-settings-block">
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Active', 'creatorreactor' ); ?></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_cloud_active]" value="0" />
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_cloud_active]" value="1" <?php checked( ! empty( $opts['creatorreactor_cloud_active'] ) ); ?> />
					<?php esc_html_e( 'Enable CreatorReactor Cloud', 'creatorreactor' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Checked means installed/enabled. Unchecked means uninstalled/disabled.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Your CreatorReactor ID', 'creatorreactor' ); ?></th>
			<td>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_cloud_id]" value="<?php echo esc_attr( $opts['creatorreactor_cloud_id'] ?? '' ); ?>" autocomplete="off" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'CreatorReactor Password', 'creatorreactor' ); ?></th>
			<td>
				<input type="password" class="regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_cloud_password]" value="<?php echo esc_attr( $cloud_password_mask ); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
				<p class="description"><?php esc_html_e( 'Stored encrypted. Leave as ******** to keep the existing value.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
