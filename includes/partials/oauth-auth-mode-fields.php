<?php
/**
 * OAuth tab body: OAuth configuration (+ Broker/API when Agency).
 *
 * Expected variables: $option_name, $broker_mode, $opts, $secret_mask, $current_product_label.
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
	<?php if ( ! $broker_mode ) : ?>
		<div class="creatorreactor-mode-notice direct">
			<p><strong><?php esc_html_e( 'Creator authentication:', 'creatorreactor' ); ?></strong> <?php printf( esc_html__( 'This plugin will authenticate directly with %s using the OAuth credentials below.', 'creatorreactor' ), esc_html( $current_product_label ) ); ?></p>
		</div>
	<?php else : ?>
		<div class="creatorreactor-mode-notice broker">
			<p><strong><?php esc_html_e( 'Agency authentication:', 'creatorreactor' ); ?></strong> <?php esc_html_e( 'Required: Broker URL and Site ID (Broker Settings below). OAuth Client ID, redirect URI, and scopes in this section are optional — they are only added to the broker connect link when set. Client Secret is not used in Agency mode.', 'creatorreactor' ); ?></p>
		</div>
	<?php endif; ?>
</div>
<div class="creatorreactor-settings-block">
	<div class="creatorreactor-oauth-configuration">
	<h3><?php esc_html_e( 'OAuth Configuration', 'creatorreactor' ); ?></h3>
	<div class="creatorreactor-subsection">
		<h4><?php esc_html_e( 'Credentials', 'creatorreactor' ); ?></h4>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Client ID', 'creatorreactor' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_client_id]" value="<?php echo esc_attr( $opts['creatorreactor_oauth_client_id'] ?? '' ); ?>" class="regular-text" />
					<?php if ( ! $broker_mode ) : ?>
						<p class="description"><?php printf( esc_html__( 'Required. Client ID from your %1$s OAuth app (Fanvue developer portal). If Fanvue shows a branded 404 when you click Connect, the Client ID does not match an active app—recopy it or create a new OAuth app.', 'creatorreactor' ), esc_html( $current_product_label ) ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Optional. Passed to the broker connect URL when set.', 'creatorreactor' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Client Secret', 'creatorreactor' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_client_secret]" value="<?php echo esc_attr( $secret_mask ); ?>" class="regular-text code creatorreactor-oauth-client-secret" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" inputmode="text" />
					<?php if ( ! $broker_mode ) : ?>
						<p class="description"><?php esc_html_e( 'Required for Creator mode. Stored encrypted. Leave as ******** to keep the existing value.', 'creatorreactor' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Not used in Agency mode (broker uses JWT after connect). You may leave blank.', 'creatorreactor' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redirect URI', 'creatorreactor' ); ?></th>
				<td>
					<div class="creatorreactor-redirect-uri-row">
						<input type="text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_redirect_uri]" value="<?php echo esc_attr( $redirect_uri_input_value ); ?>" class="regular-text code creatorreactor-oauth-redirect-uri-input" autocomplete="off" />
						<button type="button" class="button creatorreactor-copy-redirect-uri" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'creatorreactor' ); ?></button>
					</div>
					<?php if ( ! $broker_mode ) : ?>
						<p class="description"><?php esc_html_e( 'Must match your Fanvue app redirect list exactly (same scheme, host, path, and trailing slash). Use Copy, register that URL in Fanvue, save here, then Connect.', 'creatorreactor' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Optional. When set, must match what the broker and OAuth provider expect. Default below is this site’s broker callback.', 'creatorreactor' ); ?></p>
						<p class="description">
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( Broker_Client::get_default_redirect_uri() ); ?>"><?php esc_html_e( 'Copy broker callback URL', 'creatorreactor' ); ?></button>
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( Broker_Client::get_legacy_fanvue_redirect_uri() ); ?>"><?php esc_html_e( 'Copy legacy fanvue/v1 broker URL', 'creatorreactor' ); ?></button>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>
	<div class="creatorreactor-subsection creatorreactor-advanced">
		<button type="button" class="creatorreactor-advanced-toggle" aria-expanded="false" aria-controls="creatorreactor-oauth-advanced-panel">
			<span class="dashicons dashicons-arrow-down-alt2 creatorreactor-advanced-toggle-icon" aria-hidden="true"></span>
			<span class="creatorreactor-advanced-toggle-label"><?php esc_html_e( 'Advanced', 'creatorreactor' ); ?></span>
		</button>
		<div id="creatorreactor-oauth-advanced-panel" class="creatorreactor-advanced-panel" hidden>
			<div class="creatorreactor-advanced-panel-inner">
				<div class="creatorreactor-advanced-toolbar">
					<button type="button" class="button creatorreactor-advanced-lock" aria-pressed="true"
						data-label-locked="<?php echo esc_attr( __( 'Endpoint fields locked — click to unlock editing', 'creatorreactor' ) ); ?>"
						data-label-unlocked="<?php echo esc_attr( __( 'Endpoint fields unlocked — click to lock', 'creatorreactor' ) ); ?>"
						aria-label="<?php echo esc_attr( __( 'Endpoint fields locked — click to unlock editing', 'creatorreactor' ) ); ?>">
						<span class="dashicons dashicons-lock creatorreactor-advanced-lock-icon-on" aria-hidden="true"></span>
						<span class="dashicons dashicons-unlock creatorreactor-advanced-lock-icon-off" aria-hidden="true"></span>
					</button>
					<span class="description creatorreactor-advanced-lock-hint"><?php esc_html_e( 'Defaults match Fanvue. Unlock to edit authorization, token, API base, and scopes.', 'creatorreactor' ); ?></span>
				</div>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Authorization URL', 'creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_authorization_url]" value="<?php echo esc_attr( $opts['creatorreactor_authorization_url'] ?? CreatorReactor_OAuth::AUTH_URL ); ?>" class="regular-text code creatorreactor-advanced-endpoint-input" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Token URL', 'creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_token_url]" value="<?php echo esc_attr( $opts['creatorreactor_token_url'] ?? CreatorReactor_OAuth::TOKEN_URL ); ?>" class="regular-text code creatorreactor-advanced-endpoint-input" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Base URL', 'creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_api_base_url]" value="<?php echo esc_attr( $opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL ); ?>" class="regular-text code creatorreactor-advanced-endpoint-input" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Scopes', 'creatorreactor' ); ?></th>
						<td>
							<input type="text" readonly name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_oauth_scopes]" value="<?php echo esc_attr( CreatorReactor_OAuth::normalize_scopes_string( $opts['creatorreactor_oauth_scopes'] ?? '' ) ); ?>" class="regular-text creatorreactor-advanced-endpoint-input" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Space-separated OAuth scopes. Defaults include read:fan (required for Fanvue subscriber and follower APIs). Unlock Advanced to edit.', 'creatorreactor' ); ?></p>
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
	<h3><?php esc_html_e( 'Broker Settings', 'creatorreactor' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Broker URL', 'creatorreactor' ); ?></th>
			<td>
				<input type="text" class="creatorreactor-broker-field regular-text" name="<?php echo esc_attr( $option_name ); ?>[broker_url]" value="<?php echo esc_attr( $opts['broker_url'] ?? 'https://auth.ncdlabs.com' ); ?>" />
				<p class="description"><?php esc_html_e( 'The URL of the OAuth broker service.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Site ID', 'creatorreactor' ); ?></th>
			<td>
				<input type="text" class="creatorreactor-broker-field regular-text" name="<?php echo esc_attr( $option_name ); ?>[site_id]" value="<?php echo esc_attr( $opts['site_id'] ?? '' ); ?>" />
				<p class="description"><?php esc_html_e( 'Your unique site identifier from the broker admin.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<div class="creatorreactor-settings-block">
	<h3><?php esc_html_e( 'API Settings', 'creatorreactor' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'API Version', 'creatorreactor' ); ?></th>
			<td>
				<input type="text" class="creatorreactor-agency-api-field regular-text code" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_api_version]" value="<?php echo esc_attr( $opts['creatorreactor_api_version'] ?? '2025-06-26' ); ?>" />
				<p class="description"><?php esc_html_e( 'Date-based API version header.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<?php endif; ?>
