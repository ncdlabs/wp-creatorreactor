<?php
/**
 * OAuth tab body: OAuth configuration (+ Broker/API when Agency).
 *
 * Expected variables: $option_name, $broker_mode, $opts, $secret_mask, $current_product_label,
 * $fanvue_oauth_instructions_expanded (bool: true = render instructions <details> open).
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$redirect_uri_input_value = Admin_Settings::get_redirect_uri_input_value( $opts, $broker_mode );

?>
<div class="creatorreactor-settings-block">
<details class="creatorreactor-mode-notice direct creatorreactor-google-instructions"<?php echo ! empty( $fanvue_oauth_instructions_expanded ) ? ' open' : ''; ?>>
	<summary class="creatorreactor-google-instructions-summary">
		<?php if ( $broker_mode ) : ?>
			<?php esc_html_e( 'Instructions: Agency (broker) setup', 'wp-creatorreactor' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Instructions: Setting up Fanvue', 'wp-creatorreactor' ); ?>
		<?php endif; ?>
	</summary>
	<div class="creatorreactor-google-instructions-content">
		<?php if ( ! $broker_mode ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: Product name (e.g. Fanvue). */
					esc_html__( 'Create an OAuth app in the %s developer portal, register the redirect URLs from OAuth Configuration below, then paste the Client ID and Client Secret.', 'wp-creatorreactor' ),
					esc_html( $current_product_label )
				);
				?>
			</p>
			<ol>
				<li><?php esc_html_e( 'Log into your Fanvue account.', 'wp-creatorreactor' ); ?></li>
				<li>
					<?php
					printf(
						wp_kses_post(
							/* translators: %s: Fanvue developer apps URL */
							__( 'Go to CREATOR TOOL → BUILD<br /><span class="creatorreactor-auth-mode-hint-url"><a href="%s" target="_blank" rel="noopener noreferrer">https://www.fanvue.com/developers/apps</a></span>', 'wp-creatorreactor' )
						),
						esc_url( 'https://www.fanvue.com/developers/apps' )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Create an app (recommended name: CreatorReactor-OAuth).', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Add both the Redirect URI and the Fan login redirect URI from OAuth Configuration below to your Fanvue app’s redirect list. They must match exactly (scheme, host, path, trailing slash).', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Copy the Client ID and Client Secret into OAuth Configuration below, save settings, then use Connect in the plugin to authorize.', 'wp-creatorreactor' ); ?></li>
			</ol>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Agency mode uses the broker for authorization. Broker URL and Site ID are required; OAuth fields below are optional and only passed through when set.', 'wp-creatorreactor' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'Obtain your Site ID from the broker admin for this site.', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Enter Broker URL and Site ID under Broker Settings below.', 'wp-creatorreactor' ); ?></li>
				<li><?php esc_html_e( 'Optionally fill OAuth Client ID, redirect URI, and scopes if your broker connect link requires them. Client Secret is not used in Agency mode.', 'wp-creatorreactor' ); ?></li>
			</ol>
		<?php endif; ?>
	</div>
</details>
	<div class="creatorreactor-oauth-configuration">
	<h3><?php esc_html_e( 'OAuth Configuration', 'wp-creatorreactor' ); ?></h3>
	<div class="creatorreactor-subsection">
		<h4><?php esc_html_e( 'Credentials', 'wp-creatorreactor' ); ?></h4>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Client ID', 'wp-creatorreactor' ); ?></th>
				<td>
					<input type="text" id="creatorreactor_oauth_client_id" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_client_id]" value="<?php echo esc_attr( $opts['creatorreactor_oauth_client_id'] ?? '' ); ?>" class="regular-text" />
					<?php if ( ! $broker_mode ) : ?>
						<p class="description"><?php
						printf(
							/* translators: %1$s: Product name (e.g. Fanvue). */
							esc_html__( 'Required. Client ID from your %1$s OAuth app (Fanvue developer portal). If Fanvue shows a branded 404 when you click Connect, the Client ID does not match an active app—recopy it or create a new OAuth app.', 'wp-creatorreactor' ),
							esc_html( $current_product_label )
						);
						?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Optional. Passed to the broker connect URL when set.', 'wp-creatorreactor' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Client Secret', 'wp-creatorreactor' ); ?></th>
				<td>
					<input type="text" id="creatorreactor_oauth_client_secret" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_client_secret]" value="<?php echo esc_attr( $secret_mask ); ?>" class="regular-text code creatorreactor-oauth-client-secret" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
					<?php if ( ! $broker_mode ) : ?>
						<p class="description"><?php esc_html_e( 'Required for Creator mode. Stored encrypted. Leave as ******** to keep the existing value.', 'wp-creatorreactor' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Not used in Agency mode (broker uses JWT after connect). You may leave blank.', 'wp-creatorreactor' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redirect URI', 'wp-creatorreactor' ); ?></th>
				<td>
					<div class="creatorreactor-redirect-uri-row">
						<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_redirect_uri]" value="<?php echo esc_attr( $redirect_uri_input_value ); ?>" class="regular-text code creatorreactor-oauth-redirect-uri-input creatorreactor-oauth-redirect-uri-readonly" autocomplete="off" aria-readonly="true" />
						<button type="button" class="button creatorreactor-copy-redirect-uri" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
					</div>
					<?php if ( ! $broker_mode ) : ?>
						<p class="description"><?php esc_html_e( 'Site connection (admin “Connect” and API sync): register this URL in your Fanvue OAuth app, together with the Fan login URL in the next row. Both must match exactly (same scheme, host, path, and trailing slash). Use Copy, add both URLs in Fanvue, save here, then Connect.', 'wp-creatorreactor' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Optional. When set, must match what the broker and OAuth provider expect. Default below is this site’s broker callback.', 'wp-creatorreactor' ); ?></p>
						<p class="description">
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( Broker_Client::get_default_redirect_uri() ); ?>"><?php esc_html_e( 'Copy broker callback URL', 'wp-creatorreactor' ); ?></button>
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( Broker_Client::get_legacy_fanvue_redirect_uri() ); ?>"><?php esc_html_e( 'Copy legacy fanvue/v1 broker URL', 'wp-creatorreactor' ); ?></button>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! $broker_mode ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fan login redirect URI', 'wp-creatorreactor' ); ?></th>
				<td>
					<div class="creatorreactor-redirect-uri-row">
						<input type="text" readonly class="regular-text code creatorreactor-oauth-redirect-uri-input creatorreactor-oauth-redirect-uri-readonly" value="<?php echo esc_attr( Fan_OAuth::get_callback_redirect_uri() ); ?>" autocomplete="off" aria-readonly="true" />
						<button type="button" class="button creatorreactor-copy-redirect-uri" aria-label="<?php esc_attr_e( 'Copy fan login redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Register this URL in the same Fanvue OAuth app’s redirect list as the site Redirect URI (above). Required for Fanvue login shortcodes, block, and wp-login when social login is enabled.', 'wp-creatorreactor' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Scopes', 'wp-creatorreactor' ); ?></th>
				<td>
					<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_scopes]" value="<?php echo esc_attr( CreatorReactor_OAuth::normalize_scopes_string( $opts['creatorreactor_oauth_scopes'] ?? '' ) ); ?>" class="regular-text creatorreactor-oauth-scopes-readonly" autocomplete="off" aria-readonly="true" />
					<p class="description"><?php esc_html_e( 'Space-separated OAuth scopes. Defaults match Fanvue Quick Start. If you enable read:fan for your app in the Fanvue developer portal, add read:fan here too, save settings, then disconnect OAuth and connect again so WordPress gets a new token that includes it.', 'wp-creatorreactor' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
	<div class="creatorreactor-subsection creatorreactor-advanced">
		<button type="button" class="creatorreactor-advanced-toggle" aria-expanded="false" aria-controls="creatorreactor-oauth-advanced-panel">
			<span class="dashicons dashicons-arrow-down-alt2 creatorreactor-advanced-toggle-icon" aria-hidden="true"></span>
			<span class="creatorreactor-advanced-toggle-label"><?php esc_html_e( 'Advanced', 'wp-creatorreactor' ); ?></span>
		</button>
		<div id="creatorreactor-oauth-advanced-panel" class="creatorreactor-advanced-panel" hidden>
			<div class="creatorreactor-advanced-panel-inner">
				<p class="description creatorreactor-advanced-hint"><?php esc_html_e( 'Defaults match Fanvue. Use the lock next to the OAuth heading above to edit authorization, token, and API base.', 'wp-creatorreactor' ); ?></p>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Authorization URL', 'wp-creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_authorization_url]" value="<?php echo esc_attr( $opts['creatorreactor_authorization_url'] ?? CreatorReactor_OAuth::AUTH_URL ); ?>" class="regular-text code creatorreactor-advanced-endpoint-input" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Token URL', 'wp-creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_token_url]" value="<?php echo esc_attr( $opts['creatorreactor_token_url'] ?? CreatorReactor_OAuth::TOKEN_URL ); ?>" class="regular-text code creatorreactor-advanced-endpoint-input" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Base URL', 'wp-creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_api_base_url]" value="<?php echo esc_attr( $opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL ); ?>" class="regular-text code creatorreactor-advanced-endpoint-input" autocomplete="off" />
						</td>
					</tr>
				</table>
			</div>
		</div>
	</div>
	</div>
</div>
<?php if ( $broker_mode ) : ?>
<div class="creatorreactor-settings-block">
	<h3><?php esc_html_e( 'Broker Settings', 'wp-creatorreactor' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Broker URL', 'wp-creatorreactor' ); ?></th>
			<td>
				<input type="text" class="creatorreactor-broker-field regular-text" name="<?php echo esc_attr( $option_name ); ?>[broker_url]" value="<?php echo esc_attr( $opts['broker_url'] ?? 'https://auth.ncdlabs.com' ); ?>" />
				<p class="description"><?php esc_html_e( 'The URL of the OAuth broker service.', 'wp-creatorreactor' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Site ID', 'wp-creatorreactor' ); ?></th>
			<td>
				<input type="text" class="creatorreactor-broker-field regular-text" name="<?php echo esc_attr( $option_name ); ?>[site_id]" value="<?php echo esc_attr( $opts['site_id'] ?? '' ); ?>" />
				<p class="description"><?php esc_html_e( 'Your unique site identifier from the broker admin.', 'wp-creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<div class="creatorreactor-settings-block">
	<h3><?php esc_html_e( 'API Settings', 'wp-creatorreactor' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'API Version', 'wp-creatorreactor' ); ?></th>
			<td>
				<input type="text" class="creatorreactor-agency-api-field regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_api_version]" value="<?php echo esc_attr( $opts['creatorreactor_api_version'] ?? '2025-06-26' ); ?>" />
				<p class="description"><?php esc_html_e( 'Date-based API version header.', 'wp-creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<?php endif; ?>
