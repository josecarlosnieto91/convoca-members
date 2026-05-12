<?php
/**
 * Uninstall: clean up options and CPT data.
 *
 * @package Convoca\Members
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options.
delete_option('bdv_members_settings');
delete_option('bdv_email_templates');

// Delete all miembro posts and their meta.
$posts = get_posts([
    'post_type' => 'miembro',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($posts as $post_id) {
    wp_delete_post($post_id, true);
}

// Delete taxonomy terms.
$terms = get_terms([
    'taxonomy' => 'tipo_miembro',
    'hide_empty' => false,
    'fields' => 'ids',
]);

if (!is_wp_error($terms)) {
    foreach ($terms as $term_id) {
        wp_delete_term($term_id, 'tipo_miembro');
    }
}
