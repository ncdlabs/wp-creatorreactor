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

$next_sync_display = Cron::get_next_sync_datetime_for_display();

?>
<?php if ( ! $broker_mode ) : ?>
<div class="creatorreactor-settings-block">
	<h3><?php esc_html_e( 'Sync Settings', 'wp-creatorreactor' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Sync Interval', 'wp-creatorreactor' ); ?></th>
			<td>
				<div class="creatorreactor-sync-row">
					<input type="number" class="creatorreactor-creator-sync-field" name="<?php echo esc_attr( $option_name ); ?>[cron_interval_minutes]" value="<?php echo esc_attr( $opts['cron_interval_minutes'] ?? 15 ); ?>" min="5" max="1440" />
					<span class="description"><?php esc_html_e( 'minutes', 'wp-creatorreactor' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'How often to sync subscribers and followers.', 'wp-creatorreactor' ); ?></p>
				<p class="description creatorreactor-next-sync">
					<strong><?php esc_html_e( 'Next sync:', 'wp-creatorreactor' ); ?></strong>
					<?php if ( $next_sync_display !== null ) : ?>
						<?php echo esc_html( $next_sync_display ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Not scheduled', 'wp-creatorreactor' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Cache TTL', 'wp-creatorreactor' ); ?></th>
			<td>
				<div class="creatorreactor-sync-row">
					<input type="number" class="creatorreactor-creator-sync-field" name="<?php echo esc_attr( $option_name ); ?>[entitlement_cache_ttl_seconds]" value="<?php echo esc_attr( $opts['entitlement_cache_ttl_seconds'] ?? 900 ); ?>" min="60" />
					<span class="description"><?php esc_html_e( 'seconds', 'wp-creatorreactor' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'How long entitlements are valid before requiring a new sync.', 'wp-creatorreactor' ); ?></p>
			</td>
		</tr>
	</table>
</div>
<?php else : ?>
<div class="creatorreactor-settings-block">
	<p class="description"><?php esc_html_e( 'Sync settings for Agency mode are managed through your broker configuration.', 'wp-creatorreactor' ); ?></p>
	<p class="description creatorreactor-next-sync">
		<strong><?php esc_html_e( 'Next sync:', 'wp-creatorreactor' ); ?></strong>
		<?php if ( $next_sync_display !== null ) : ?>
			<?php echo esc_html( $next_sync_display ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Not scheduled', 'wp-creatorreactor' ); ?>
		<?php endif; ?>
	</p>
</div>
<?php endif; ?>
