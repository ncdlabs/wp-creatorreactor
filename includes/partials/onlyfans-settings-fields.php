<?php
/**
 * OnlyFans tab: OAuth + OFAuth Configuration, Login Button Appearance.
 *
 * Expected variables: $option_name, $opts, $api_key_mask, $webhook_secret_mask,
 * $webhook_url, $settings_cancel_url, $onlyfans_ofauth_instructions_expanded (bool).
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$of_mode = Admin_Settings::get_onlyfans_oauth_button_size_mode();
?>
<div id="creatorreactor-onlyfans-settings-dynamic">
<div class="creatorreactor-settings-form-card creatorreactor-onlyfans-settings-primary-card">
	<div class="creatorreactor-settings-panel is-active" data-subtab="oauth">
		<h2><?php esc_html_e( 'OAuth', 'wp-creatorreactor' ); ?></h2>
		<div class="creatorreactor-settings-block">
			<details class="creatorreactor-mode-notice direct creatorreactor-google-instructions"<?php echo ! empty( $onlyfans_ofauth_instructions_expanded ) ? ' open' : ''; ?>>
				<summary class="creatorreactor-google-instructions-summary">
					<?php esc_html_e( 'Instructions: Setting up OFAuth', 'wp-creatorreactor' ); ?>
				</summary>
				<div class="creatorreactor-google-instructions-content">
					<ol>
						<li>
							<?php
							printf(
								wp_kses_post(
									/* translators: 1: opening anchor tag (OFAuth dashboard), 2: closing anchor tag */
									__( 'In the %1$sOFAuth dashboard%2$s, generate an access key with Account Linking permissions, then paste it into the API key field under OFAuth Configuration below.', 'wp-creatorreactor' )
								),
								'<a href="' . esc_url( 'https://ofauth.com/dashboard/account/keys' ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								wp_kses_post(
									/* translators: 1: opening anchor tag (OFAuth dashboard), 2: closing anchor tag */
									__( 'In the %1$sOFAuth dashboard%2$s, set the webhook URL to the value shown under OFAuth Configuration below so OFAuth can POST Account Linking events to this site.', 'wp-creatorreactor' )
								),
								'<a href="' . esc_url( 'https://ofauth.com/dashboard/webhooks' ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								wp_kses_post(
									/* translators: 1: opening anchor tag (OFAuth dashboard), 2: closing anchor tag */
									__( 'In the %1$sOFAuth dashboard%2$s, configure the same webhook secret as in the Webhook secret field below. Incoming requests send it as the x-webhook-secret header.', 'wp-creatorreactor' )
								),
								'<a href="' . esc_url( 'https://ofauth.com/dashboard/webhooks' ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
						</li>
					</ol>
					<p class="description creatorreactor-ofauth-doc-link">
						<?php
						printf(
							wp_kses_post(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								__( 'See the %1$sOFAuth integration guide%2$s (prerequisites and implementation).', 'wp-creatorreactor' )
							),
							'<a href="' . esc_url( 'https://docs.ofauth.com/guide/OnlyFans-authentication/Integrating#prerequisites' ) . '" target="_blank" rel="noopener noreferrer">',
							'</a>'
						);
						?>
					</p>
				</div>
			</details>
			<h3><?php esc_html_e( 'OFAuth Configuration', 'wp-creatorreactor' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'API key', 'wp-creatorreactor' ); ?></th>
					<td>
						<input type="text" id="creatorreactor_ofauth_api_key" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_api_key]" value="<?php echo esc_attr( $api_key_mask ); ?>" class="regular-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
						<p class="description"><?php esc_html_e( 'OFAuth access key with Account Linking permissions. Stored encrypted. Leave as ******** to keep the existing value.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook secret', 'wp-creatorreactor' ); ?></th>
					<td>
						<input type="text" id="creatorreactor_ofauth_webhook_secret" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_webhook_secret]" value="<?php echo esc_attr( $webhook_secret_mask ); ?>" class="regular-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
						<p class="description"><?php esc_html_e( 'Must match the secret configured in OFAuth. Sent as the x-webhook-secret header. Stored encrypted. Leave as ******** to keep the existing value.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'wp-creatorreactor' ); ?></th>
					<td>
						<div class="creatorreactor-redirect-uri-row">
							<input type="text" readonly class="regular-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $webhook_url ); ?>" autocomplete="off" aria-readonly="true" />
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $webhook_url ); ?>" aria-label="<?php esc_attr_e( 'Copy webhook URL to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Register this POST URL in the OFAuth dashboard for Account Linking webhooks.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Success redirect URL', 'wp-creatorreactor' ); ?></th>
					<td>
						<input type="url" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_success_url]" value="<?php echo esc_attr( $opts['creatorreactor_ofauth_success_url'] ?? '' ); ?>" class="regular-text code" placeholder="https://…" />
						<p class="description"><?php esc_html_e( 'For hosted flows: where to send users after a successful link (http or https). Defaults to the site home URL when left empty.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cancel redirect URL', 'wp-creatorreactor' ); ?></th>
					<td>
						<input type="url" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_cancel_url]" value="<?php echo esc_attr( $opts['creatorreactor_ofauth_cancel_url'] ?? '' ); ?>" class="regular-text code" placeholder="https://…" />
						<p class="description"><?php esc_html_e( 'For hosted flows: where to send users if they cancel (http or https). Defaults to the site home URL when left empty.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<div class="creatorreactor-settings-actions">
		<a class="button" href="<?php echo esc_url( $settings_cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
		<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
	</div>
</div>
<div class="creatorreactor-settings-form-card creatorreactor-provider-login-appearance-card creatorreactor-onlyfans-login-appearance-card">
	<div class="creatorreactor-settings-panel is-active" data-subtab="onlyfans-oauth-appearance">
		<h2><?php esc_html_e( 'Login Button Appearance', 'wp-creatorreactor' ); ?></h2>
		<div class="creatorreactor-settings-block">
			<?php Admin_Settings::render_login_button_appearance_card_intro(); ?>
			<?php
			Admin_Settings::render_oauth_logo_button_size_mode_fieldset(
				$option_name,
				Admin_Settings::ONLYFANS_OAUTH_BUTTON_SIZE_MODE_KEY,
				$of_mode,
				Admin_Settings::get_oauth_logo_button_general_size(),
				Shortcodes::onlyfans_oauth_admin_preview_chip( 'minimal' ),
				Shortcodes::onlyfans_oauth_admin_preview_chip( 'standard' )
			);
			?>
		</div>
	</div>
	<div class="creatorreactor-settings-actions">
		<a class="button" href="<?php echo esc_url( $settings_cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'wp-creatorreactor' ); ?></a>
		<?php submit_button( __( 'Save Settings', 'wp-creatorreactor' ) ); ?>
	</div>
</div>
</div>
