<?php
/**
 * OnlyFans tab: OFAuth credentials (OAuth subtab) + shared Sync settings (Sync subtab).
 *
 * Expected variables: $option_name, $opts, $api_key_mask, $webhook_secret_mask,
 * $onlyfans_active_subtab, $webhook_url, $settings_cancel_url.
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$broker_mode = ! empty( $opts['broker_mode'] );

?>
<div id="creatorreactor-onlyfans-settings-dynamic" class="creatorreactor-settings-form-card">
	<div class="creatorreactor-settings-panel <?php echo 'oauth' === $onlyfans_active_subtab ? 'is-active' : ''; ?>" data-subtab="oauth">
		<h2><?php esc_html_e( 'OAuth', 'creatorreactor' ); ?></h2>
		<div class="creatorreactor-settings-block">
			<div class="creatorreactor-mode-notice direct">
				<p><strong><?php esc_html_e( 'How to get your API key and webhook details', 'creatorreactor' ); ?></strong></p>
				<ol>
					<li>
						<?php
						printf(
							wp_kses_post(
								/* translators: 1: opening anchor tag (OFAuth dashboard), 2: closing anchor tag */
								__( 'In the %1$sOFAuth dashboard%2$s, generate an access key with Account Linking permissions, then paste it into the API key field below.', 'creatorreactor' )
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
								__( 'In the %1$sOFAuth dashboard%2$s, set the webhook URL to the value shown below so OFAuth can POST Account Linking events to this site.', 'creatorreactor' )
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
								__( 'In the %1$sOFAuth dashboard%2$s, configure the same webhook secret as in the Webhook secret field below. Incoming requests send it as the x-webhook-secret header.', 'creatorreactor' )
							),
							'<a href="' . esc_url( 'https://ofauth.com/dashboard/webhooks' ) . '" target="_blank" rel="noopener noreferrer">',
							'</a>'
						);
						?>
					</li>
				</ol>
				<p class="creatorreactor-ofauth-doc-link">
					<?php
					printf(
						wp_kses_post(
							/* translators: 1: opening anchor tag, 2: closing anchor tag */
							__( 'See the %1$sOFAuth integration guide%2$s (prerequisites and implementation).', 'creatorreactor' )
						),
						'<a href="' . esc_url( 'https://docs.ofauth.com/guide/OnlyFans-authentication/Integrating#prerequisites' ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					);
					?>
				</p>
			</div>
		</div>
		<div class="creatorreactor-settings-block">
			<h3><?php esc_html_e( 'OFAuth credentials', 'creatorreactor' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'API key', 'creatorreactor' ); ?></th>
					<td>
						<input type="text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_api_key]" value="<?php echo esc_attr( $api_key_mask ); ?>" class="regular-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
						<p class="description"><?php esc_html_e( 'OFAuth access key with Account Linking permissions. Stored encrypted. Leave as ******** to keep the existing value.', 'creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook secret', 'creatorreactor' ); ?></th>
					<td>
						<input type="text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_webhook_secret]" value="<?php echo esc_attr( $webhook_secret_mask ); ?>" class="regular-text code" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
						<p class="description"><?php esc_html_e( 'Must match the secret configured in OFAuth. Sent as the x-webhook-secret header. Stored encrypted. Leave as ******** to keep the existing value.', 'creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'creatorreactor' ); ?></th>
					<td>
						<div class="creatorreactor-redirect-uri-row">
							<input type="text" readonly class="regular-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $webhook_url ); ?>" autocomplete="off" aria-readonly="true" />
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $webhook_url ); ?>" aria-label="<?php esc_attr_e( 'Copy webhook URL to clipboard', 'creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'creatorreactor' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Register this POST URL in the OFAuth dashboard for Account Linking webhooks.', 'creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Success redirect URL', 'creatorreactor' ); ?></th>
					<td>
						<input type="url" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_success_url]" value="<?php echo esc_attr( $opts['creatorreactor_ofauth_success_url'] ?? '' ); ?>" class="regular-text code" placeholder="https://…" />
						<p class="description"><?php esc_html_e( 'For hosted flows: where to send users after a successful link (http or https). Defaults to the site home URL when left empty.', 'creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cancel redirect URL', 'creatorreactor' ); ?></th>
					<td>
						<input type="url" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_ofauth_cancel_url]" value="<?php echo esc_attr( $opts['creatorreactor_ofauth_cancel_url'] ?? '' ); ?>" class="regular-text code" placeholder="https://…" />
						<p class="description"><?php esc_html_e( 'For hosted flows: where to send users if they cancel (http or https). Defaults to the site home URL when left empty.', 'creatorreactor' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<div class="creatorreactor-settings-panel <?php echo 'sync' === $onlyfans_active_subtab ? 'is-active' : ''; ?>" data-subtab="sync">
		<h2><?php esc_html_e( 'Sync', 'creatorreactor' ); ?></h2>
		<div id="creatorreactor-onlyfans-sync-dynamic" class="creatorreactor-auth-mode-dynamic" tabindex="-1">
			<?php include CREATORREACTOR_PLUGIN_DIR . 'includes/partials/sync-auth-mode-fields.php'; ?>
		</div>
	</div>
	<div class="creatorreactor-settings-actions">
		<a class="button" href="<?php echo esc_url( $settings_cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'creatorreactor' ); ?></a>
		<?php submit_button( __( 'Save Settings', 'creatorreactor' ) ); ?>
	</div>
</div>
