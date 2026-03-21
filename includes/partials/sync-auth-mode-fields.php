<?php
/**
 * Sync tab body for Creator vs Agency.
 *
 * Expected variables: $option_name, $broker_mode, $opts.
 *
 * @package CreatorReactor
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php if ( ! $broker_mode ) : ?>
<div class="creatorreactor-settings-block">
	<h3><?php esc_html_e( 'Sync Settings', 'creatorreactor' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Creator ID', 'creatorreactor' ); ?></th>
			<td>
				<input type="text" name="<?php echo esc_attr( $option_name ); ?>[creatorreactor_creator_id]" value="<?php echo esc_attr( $opts['creatorreactor_creator_id'] ?? '' ); ?>" class="creatorreactor-creator-sync-field regular-text" />
				<p class="description"><?php esc_html_e( 'Optional: Filter subscribers to a specific creator.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Sync Interval', 'creatorreactor' ); ?></th>
			<td>
				<div class="creatorreactor-sync-row">
					<input type="number" class="creatorreactor-creator-sync-field" name="<?php echo esc_attr( $option_name ); ?>[cron_interval_minutes]" value="<?php echo esc_attr( $opts['cron_interval_minutes'] ?? 15 ); ?>" min="5" max="1440" />
					<span class="description"><?php esc_html_e( 'minutes', 'creatorreactor' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'How often to sync subscribers and followers.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Cache TTL', 'creatorreactor' ); ?></th>
			<td>
				<div class="creatorreactor-sync-row">
					<input type="number" class="creatorreactor-creator-sync-field" name="<?php echo esc_attr( $option_name ); ?>[entitlement_cache_ttl_seconds]" value="<?php echo esc_attr( $opts['entitlement_cache_ttl_seconds'] ?? 900 ); ?>" min="60" />
					<span class="description"><?php esc_html_e( 'seconds', 'creatorreactor' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'How long entitlements are valid before requiring a new sync.', 'creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<?php else : ?>
<div class="creatorreactor-settings-block">
	<p class="description"><?php esc_html_e( 'Sync settings for Agency mode are managed through your broker configuration.', 'creatorreactor' ); ?></p>
</div>
<?php endif; ?>
