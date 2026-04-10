<?php
/**
 * Settings tab: generic social OAuth provider (credentials + appearance).
 *
 * @package CreatorReactor
 *
 * Variables: $slug, $option_name, $opts, $secret_mask, $broker_mode, $redirect_uri, $label, $instructions_expanded
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CreatorReactor\Admin_Settings;
use CreatorReactor\Shortcodes;
use CreatorReactor\Social_OAuth_Registry;

$id_key   = Social_OAuth_Registry::option_client_id( $slug );
$sec_key  = Social_OAuth_Registry::option_client_secret( $slug );
$mode_key = Social_OAuth_Registry::option_button_size_mode( $slug );
$cid      = isset( $opts[ $id_key ] ) ? (string) $opts[ $id_key ] : '';
$mastodon = isset( $opts['creatorreactor_mastodon_instance'] ) ? (string) $opts['creatorreactor_mastodon_instance'] : '';
$disable_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=creatorreactor_disable_social_oauth&provider_slug=' . rawurlencode( (string) $slug ) ),
	'creatorreactor_disable_social_oauth'
);
?>
<div class="creatorreactor-settings-form-card">
<div class="creatorreactor-settings-panel is-active" data-subtab="<?php echo esc_attr( $slug ); ?>-oauth">
	<h2><?php echo esc_html( sprintf( /* translators: %s: provider name */ __( 'Sign in with %s', 'wp-creatorreactor' ), $label ) ); ?></h2>
	<?php if ( $broker_mode ) : ?>
		<div class="creatorreactor-mode-notice broker">
			<p><?php esc_html_e( 'Social sign-in for this provider is not used in Agency (broker) mode.', 'wp-creatorreactor' ); ?></p>
		</div>
	<?php else : ?>
		<div class="creatorreactor-settings-block">
			<details class="creatorreactor-mode-notice direct creatorreactor-social-oauth-instructions"<?php echo $instructions_expanded ? ' open' : ''; ?>>
				<summary class="creatorreactor-social-oauth-instructions-summary"><?php echo esc_html( sprintf( /* translators: %s: provider name */ __( 'Instructions: %s OAuth app', 'wp-creatorreactor' ), $label ) ); ?></summary>
				<div class="creatorreactor-social-oauth-instructions-content">
					<?php if ( 'bluesky' === $slug ) : ?>
						<p class="description"><?php esc_html_e( 'Bluesky uses atproto OAuth (client metadata URL, PAR, DPoP). Register an OAuth client, set the redirect URI below exactly, and paste credentials from the developer flow.', 'wp-creatorreactor' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Create an OAuth 2.0 (web) application in the provider’s developer console. Use the redirect URI below (including any trailing slash).', 'wp-creatorreactor' ); ?></p>
					<?php endif; ?>
					<ol>
						<li><?php esc_html_e( 'Copy the Authorized redirect URI into your app registration:', 'wp-creatorreactor' ); ?>
							<div class="creatorreactor-redirect-uri-row">
								<input type="text" readonly class="large-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $redirect_uri ); ?>" autocomplete="off" aria-readonly="true" />
								<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $redirect_uri ); ?>" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
							</div>
						</li>
						<?php if ( 'mastodon' === $slug ) : ?>
							<li><?php esc_html_e( 'Enter your instance base URL below and register the app with the redirect URI above.', 'wp-creatorreactor' ); ?></li>
						<?php elseif ( 'patreon' === $slug ) : ?>
							<li>
								<?php
								printf(
									wp_kses_post(
										/* translators: 1: opening link to Patreon client registration, 2: closing link */
										__( 'Register an OAuth client under %1$sClients & API Keys%2$s (creator account). Request API v2 scopes: identity and identity[email] so WordPress can receive profile id and email.', 'wp-creatorreactor' )
									),
									'<a href="' . esc_url( 'https://www.patreon.com/portal/registration/register-clients' ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
								);
								?>
							</li>
						<?php endif; ?>
					</ol>
				</div>
			</details>
			<table class="form-table" role="presentation">
				<?php if ( 'bluesky' === $slug ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Bluesky', 'wp-creatorreactor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_bluesky_oauth_enabled]" value="1" <?php checked( ! empty( $opts['creatorreactor_bluesky_oauth_enabled'] ) ); ?> />
							<?php esc_html_e( 'Allow visitors to sign in with Bluesky (atproto OAuth, public client).', 'wp-creatorreactor' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'The plugin publishes OAuth client metadata at a REST URL; use that URL as client_id when registering your app.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( 'mastodon' === $slug ) : ?>
				<tr>
					<th scope="row"><label for="creatorreactor_mastodon_instance"><?php esc_html_e( 'Instance URL', 'wp-creatorreactor' ); ?></label></th>
					<td>
						<input type="url" id="creatorreactor_mastodon_instance" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_mastodon_instance]" value="<?php echo esc_attr( $mastodon ); ?>" class="large-text code" placeholder="https://mastodon.social" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'HTTPS base URL of your Mastodon server (no path).', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $id_key ); ?>"><?php esc_html_e( 'Client ID', 'wp-creatorreactor' ); ?></label></th>
					<td>
						<input type="text" id="<?php echo esc_attr( $id_key ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $id_key ); ?>]" value="<?php echo esc_attr( $cid ); ?>" class="large-text code creatorreactor-social-oauth-client-id" data-provider-slug="<?php echo esc_attr( $slug ); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
						<p class="description"><?php esc_html_e( 'Stored encrypted.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $sec_key ); ?>"><?php esc_html_e( 'Client Secret', 'wp-creatorreactor' ); ?></label></th>
					<td>
						<input type="text" id="<?php echo esc_attr( $sec_key ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $sec_key ); ?>]" value="<?php echo esc_attr( $secret_mask ); ?>" class="large-text code creatorreactor-social-oauth-client-secret" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
						<p class="description"><?php esc_html_e( 'Stored encrypted. Leave as ******** to keep the existing value.', 'wp-creatorreactor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Authorized redirect URI', 'wp-creatorreactor' ); ?></th>
					<td>
						<div class="creatorreactor-redirect-uri-row">
							<input type="text" readonly class="large-text code creatorreactor-oauth-redirect-uri-input" value="<?php echo esc_attr( $redirect_uri ); ?>" autocomplete="off" aria-readonly="true" />
							<button type="button" class="button creatorreactor-copy-redirect-uri" data-copy-text="<?php echo esc_attr( $redirect_uri ); ?>" aria-label="<?php esc_attr_e( 'Copy redirect URI to clipboard', 'wp-creatorreactor' ); ?>"><?php esc_html_e( 'Copy', 'wp-creatorreactor' ); ?></button>
						</div>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" class="button creatorreactor-social-oauth-test" data-provider-slug="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Test configuration', 'wp-creatorreactor' ); ?></button>
				<a href="<?php echo esc_url( $disable_url ); ?>" class="button" style="margin-left:8px;" onclick="return window.confirm('<?php echo esc_js( __( 'Clear saved credentials for this provider?', 'wp-creatorreactor' ) ); ?>');"><?php esc_html_e( 'Disable / clear credentials', 'wp-creatorreactor' ); ?></a>
			</p>
		</div>
	<?php endif; ?>
</div>
</div>
<?php if ( empty( $broker_mode ) ) : ?>
<div class="creatorreactor-settings-form-card creatorreactor-provider-login-appearance-card">
	<div class="creatorreactor-settings-panel is-active" data-subtab="<?php echo esc_attr( $slug ); ?>-oauth-appearance">
		<h2><?php esc_html_e( 'Login Button Appearance', 'wp-creatorreactor' ); ?></h2>
		<div class="creatorreactor-settings-block">
			<?php Admin_Settings::render_login_button_appearance_card_intro(); ?>
			<?php
			$site_general = Admin_Settings::get_oauth_logo_button_general_size();
			$stored_mode  = isset( $opts[ $mode_key ] ) ? Admin_Settings::sanitize_oauth_button_size_mode( $opts[ $mode_key ] ) : 'general';
			Admin_Settings::render_oauth_logo_button_size_mode_fieldset(
				$option_name,
				$mode_key,
				$stored_mode,
				$site_general,
				Shortcodes::social_oauth_admin_preview_chip( $slug, 'compact' ),
				Shortcodes::social_oauth_admin_preview_chip( $slug, 'full' ),
				[
					'radiogroup_aria_label' => sprintf(
						/* translators: %s: provider name */
						__( '%s login button size', 'wp-creatorreactor' ),
						$label
					),
				]
			);
			?>
		</div>
	</div>
</div>
<?php endif; ?>
