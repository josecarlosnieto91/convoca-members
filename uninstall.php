<?php
/**
 * Uninstall handler for Convoca Members.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── Keep data mode ───
// Define CONVOCA_KEEP_DATA_ON_UNINSTALL in wp-config.php to preserve all data
// when uninstalling. Useful for temporary deactivation + reactivation.
if ( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

// Delete options.
delete_option( 'conv_members_settings' );
delete_option( 'conv_email_templates' );
delete_option( 'conv_members_db_version' );
delete_option( 'conv_members_plans' );
delete_option( 'conv_last_member_number' );
delete_option( 'conv_last_member_number_fallback' );

// Delete all miembro posts and their meta.
$posts = get_posts(
	array(
		'post_type'      => 'miembro',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}

// Delete taxonomy terms.
$terms = get_terms(
	array(
		'taxonomy'   => 'tipo_miembro',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'tipo_miembro' );
	}
}

// Drop custom table.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}conv_member_sequence" );

// Clear cron.
wp_clear_scheduled_hook( 'conv_members_reminder_week' );
wp_clear_scheduled_hook( 'conv_members_reminder_month' );
