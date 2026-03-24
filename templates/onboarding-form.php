<?php
/**
 * Onboarding form markup (included from {@see Onboarding::render_form()}).
 *
 * @package CreatorReactor
 * @var \WP_User $user
 * @var string   $redirect_to
 * @var string   $err
 * @var string   $terms_html
 * @var string   $email
 * @var string   $display
 * @var string   $phone
 * @var string   $address
 * @var string   $country
 * @var string   $contact_pref
 * @var bool     $opt_out_emails
 * @var string   $pending_token Set when finishing Fanvue registration before a WP user exists.
 * @var bool     $registration_closed When true (pending Fanvue flow), WP "Anyone can register" is off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pending_token = isset( $pending_token ) ? (string) $pending_token : '';
$registration_closed = ! get_option( 'users_can_register' );

$err_msgs = [
	'invalid_email'        => __( 'Please enter a valid email address.', 'creatorreactor' ),
	'tos_required'         => __( 'You must agree to the Terms of Service to continue.', 'creatorreactor' ),
	'email_in_use'         => __( 'That email address is already used by another account.', 'creatorreactor' ),
	'profile_update'       => __( 'We could not save your profile. Please try again.', 'creatorreactor' ),
	'registration_closed' => __( 'New accounts are not allowed on this site. An administrator must enable registration in WordPress settings (Settings → General → Membership) or create your account manually.', 'creatorreactor' ),
];
$show_registration_closed = $registration_closed || $err === 'registration_closed';
$err_text                   = ( $err !== '' && isset( $err_msgs[ $err ] ) && $err !== 'registration_closed' ) ? $err_msgs[ $err ] : '';
$tos_err_visible = ( $err === 'tos_required' );
if ( $tos_err_visible ) {
	$err_text = '';
}

$countries = [
	'Argentina', 'Australia', 'Austria', 'Belgium', 'Brazil', 'Canada', 'Chile', 'Colombia',
	'Denmark', 'Finland', 'France', 'Germany', 'India', 'Ireland', 'Italy', 'Japan', 'Mexico',
	'Netherlands', 'New Zealand', 'Norway', 'Poland', 'Portugal', 'Spain', 'Sweden', 'Switzerland',
	'United Kingdom', 'United States', 'Other',
];
?>
<div class="cr-ob">
	<?php if ( $show_registration_closed ) : ?>
		<div class="cr-ob__alert cr-ob__alert--warning" role="alert"><?php echo esc_html( $err_msgs['registration_closed'] ); ?></div>
	<?php elseif ( $err_text !== '' ) : ?>
		<div class="cr-ob__alert" role="alert"><?php echo esc_html( $err_text ); ?></div>
	<?php endif; ?>

	<header class="cr-ob__header">
		<h1 class="cr-ob__title"><?php esc_html_e( 'We just need one more step to finish setting up your account.', 'creatorreactor' ); ?></h1>
		<p class="cr-ob__lead"><?php
		if ( $pending_token !== '' ) {
			$pending_email_locked = ( $email !== '' && is_email( $email ) );
			echo $pending_email_locked
				? esc_html__( 'Complete your account using the email from your Fanvue sign-in (locked below), then agree to the terms.', 'creatorreactor' )
				: esc_html__( 'Enter the email you want to use on this site, then complete the fields below and agree to the terms.', 'creatorreactor' );
		} else {
			esc_html_e( 'Please enter your email address below so we can create your account and link it to your login.', 'creatorreactor' );
		}
		?></p>
	</header>

	<form id="cr-ob-form" class="cr-ob__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="<?php echo esc_attr( \CreatorReactor\Onboarding::ACTION_SUBMIT ); ?>" />
		<?php if ( $pending_token !== '' ) : ?>
			<input type="hidden" name="creatorreactor_fan_pending" value="<?php echo esc_attr( $pending_token ); ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( \CreatorReactor\Onboarding::nonce_action_pending( $pending_token ) ) ); ?>" />
		<?php else : ?>
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( \CreatorReactor\Onboarding::ACTION_SUBMIT ) ); ?>" />
		<?php endif; ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />

		<div class="cr-ob__fields">
			<div class="cr-ob__field">
				<label class="cr-ob__label" for="creatorreactor_email"><?php esc_html_e( 'Email address', 'creatorreactor' ); ?> <span class="cr-ob__req" aria-hidden="true">*</span></label>
				<input class="cr-ob__input" type="email" name="creatorreactor_email" id="creatorreactor_email" required autocomplete="email" value="<?php echo esc_attr( $email ); ?>" <?php echo ( $pending_token !== '' && $email !== '' && is_email( $email ) ) ? 'readonly aria-readonly="true"' : ''; ?> />
			</div>

			<div class="cr-ob__field">
				<label class="cr-ob__label" for="creatorreactor_display_name"><?php esc_html_e( 'Display name', 'creatorreactor' ); ?> <span class="cr-ob__optional">(<?php esc_html_e( 'optional', 'creatorreactor' ); ?>)</span></label>
				<input class="cr-ob__input" type="text" name="creatorreactor_display_name" id="creatorreactor_display_name" autocomplete="name" value="<?php echo esc_attr( $display ); ?>" />
			</div>
		</div>

		<details class="cr-ob__more">
			<summary class="cr-ob__more-summary"><?php esc_html_e( 'Additional info', 'creatorreactor' ); ?> <span class="cr-ob__optional">(<?php esc_html_e( 'optional', 'creatorreactor' ); ?>)</span></summary>
			<div class="cr-ob__more-body">
				<div class="cr-ob__field">
					<label class="cr-ob__label" for="creatorreactor_phone"><?php esc_html_e( 'Telephone', 'creatorreactor' ); ?></label>
					<input class="cr-ob__input" type="tel" name="creatorreactor_phone" id="creatorreactor_phone" inputmode="tel" autocomplete="tel" maxlength="40" value="<?php echo esc_attr( $phone ); ?>" />
					<p class="cr-ob__hint"><?php esc_html_e( 'Optional — used for SMS alerts or VIP notifications.', 'creatorreactor' ); ?></p>
				</div>
				<div class="cr-ob__field">
					<label class="cr-ob__label" for="creatorreactor_address"><?php esc_html_e( 'Address', 'creatorreactor' ); ?></label>
					<textarea class="cr-ob__textarea" name="creatorreactor_address" id="creatorreactor_address" rows="3" autocomplete="street-address"><?php echo esc_textarea( $address ); ?></textarea>
				</div>
				<div class="cr-ob__field">
					<label class="cr-ob__label" for="creatorreactor_country"><?php esc_html_e( 'Country / region', 'creatorreactor' ); ?></label>
					<input class="cr-ob__input" type="text" name="creatorreactor_country" id="creatorreactor_country" list="cr-ob-countries" autocomplete="country-name" value="<?php echo esc_attr( $country ); ?>" />
					<datalist id="cr-ob-countries">
						<?php foreach ( $countries as $c ) : ?>
							<option value="<?php echo esc_attr( $c ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
				</div>
				<fieldset class="cr-ob__field cr-ob__fieldset">
					<legend class="cr-ob__label"><?php esc_html_e( 'Preferred contact method', 'creatorreactor' ); ?></legend>
					<label class="cr-ob__radio"><input type="radio" name="creatorreactor_contact_preference" value="email" <?php checked( $contact_pref, 'email' ); ?> /> <?php esc_html_e( 'Email', 'creatorreactor' ); ?></label>
					<label class="cr-ob__radio"><input type="radio" name="creatorreactor_contact_preference" value="sms" <?php checked( $contact_pref, 'sms' ); ?> /> <?php esc_html_e( 'SMS', 'creatorreactor' ); ?></label>
					<label class="cr-ob__radio"><input type="radio" name="creatorreactor_contact_preference" value="both" <?php checked( $contact_pref, 'both' ); ?> /> <?php esc_html_e( 'Both', 'creatorreactor' ); ?></label>
				</fieldset>
			</div>
		</details>

		<div class="cr-ob__consent">
			<label class="cr-ob__check">
				<input type="checkbox" name="creatorreactor_opt_out_emails" value="1" <?php checked( $opt_out_emails ); ?> />
				<?php esc_html_e( 'Opt out from update emails from this creator', 'creatorreactor' ); ?>
			</label>
			<fieldset class="cr-ob__tos-fieldset">
				<legend class="screen-reader-text"><?php esc_html_e( 'Terms of Service acknowledgment', 'creatorreactor' ); ?></legend>
				<div id="cr-ob-tos-error" class="cr-ob__tos-error" role="alert" aria-live="polite" <?php echo $tos_err_visible ? '' : 'hidden'; ?>><?php echo $tos_err_visible ? esc_html( $err_msgs['tos_required'] ) : ''; ?></div>
				<label class="cr-ob__check cr-ob__check--tos" for="creatorreactor_tos_accept">
					<input type="checkbox" name="creatorreactor_tos_accept" id="creatorreactor_tos_accept" value="1" required aria-describedby="cr-ob-tos-error" <?php echo $tos_err_visible ? 'aria-invalid="true"' : ''; ?> />
					<span class="cr-ob__tos-line"><?php esc_html_e( 'I acknowledge and agree to the', 'creatorreactor' ); ?> </span>
					<a href="#" class="cr-ob__tos-open" id="cr-ob-tos-trigger" aria-haspopup="dialog" aria-controls="cr-ob-tos-modal" aria-expanded="false"><?php esc_html_e( 'Terms of Service', 'creatorreactor' ); ?></a>
					<span class="cr-ob__req" aria-hidden="true"> *</span>
				</label>
			</fieldset>
		</div>

		<div class="cr-ob__actions">
			<button type="submit" class="cr-ob__submit button" <?php echo $registration_closed ? 'disabled aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Complete setup', 'creatorreactor' ); ?></button>
		</div>
	</form>

	<div class="cr-ob-modal" id="cr-ob-tos-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cr-ob-tos-title" tabindex="-1">
		<div class="cr-ob-modal__backdrop" data-cr-ob-close tabindex="-1" aria-hidden="true"></div>
		<div class="cr-ob-modal__panel" role="document">
			<div class="cr-ob-modal__head">
				<h2 class="cr-ob-modal__title" id="cr-ob-tos-title"><?php esc_html_e( 'Terms of Service', 'creatorreactor' ); ?></h2>
				<button type="button" class="cr-ob-modal__close" data-cr-ob-close aria-label="<?php esc_attr_e( 'Close', 'creatorreactor' ); ?>">&times;</button>
			</div>
			<div class="cr-ob-modal__body">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML sanitized in Onboarding::render_form() via wp_kses().
				echo $terms_html;
				?>
			</div>
			<div class="cr-ob-modal__foot">
				<button type="button" class="button" data-cr-ob-close><?php esc_html_e( 'Close', 'creatorreactor' ); ?></button>
			</div>
		</div>
	</div>
</div>
