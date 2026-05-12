<?php
/**
 * Upgrade Manager for Biodevas Members.
 *
 * Handles database structure upgrades for the members plugin.
 *
 * To add a new upgrade:
 * 1. Increment BDV_MEMBERS_DB_VERSION in biodevas-members.php
 * 2. Add a callback: '1.0.1' => [$this, 'upgrade_to_1_0_1']
 * 3. Implement the private method with idempotent logic.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Upgrade_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class Members_Upgrade_Manager extends Upgrade_Manager
{
    public function __construct()
    {
        $this->init();
    }

    protected function get_db_version(): string
    {
        return defined('BDV_MEMBERS_DB_VERSION') ? BDV_MEMBERS_DB_VERSION : '0.0.0';
    }

    protected function get_option_name(): string
    {
        return 'bdv_members_db_version';
    }

    protected function get_transient_prefix(): string
    {
        return 'bdv_members';
    }

    protected function get_upgrade_callbacks(): array
    {
        return [
            '1.0.2' => [$this, 'upgrade_to_1_0_2'],
            '1.0.3' => [$this, 'upgrade_to_1_0_3'],
        ];
    }

    /**
     * Create dedicated table for member sequence to prevent deadlocks on wp_options.
     */
    protected function upgrade_to_1_0_3(): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bdv_member_sequence';
        $charset_collate = $wpdb->get_charset_collate();

        // Table is now created in the common Installer, but ensure it exists
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            INDEX member_id (member_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Idempotent: only initialize if empty
        $current_max_seq = (int) $wpdb->get_var("SELECT MAX(id) FROM $table");
        if ($current_max_seq === 0) {
            $last_number = (int) get_option('bdv_last_member_number', 0);
            
            if ($last_number > 0) {
                // Initialize the AUTO_INCREMENT to the last known number
                $wpdb->query("ALTER TABLE $table AUTO_INCREMENT = " . ($last_number + 1));
            } else {
                // Sync with existing postmeta if option is missing
                $max_postmeta = (int) $wpdb->get_var("
                    SELECT MAX(CAST(meta_value AS UNSIGNED)) 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_bdv_numero_socio'
                ");
                if ($max_postmeta > 0) {
                    $wpdb->query("ALTER TABLE $table AUTO_INCREMENT = " . ($max_postmeta + 1));
                }
            }
        }

        return true;
    }

    /**
     * Normalize _bdv_fecha_renovacion to Y-m-d.
     * Prevents issues with time components in cron queries.
     */
    protected function upgrade_to_1_0_2(): bool
    {
        global $wpdb;
        
        $metas = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_bdv_fecha_renovacion'");
        
        if (empty($metas)) {
            return true;
        }

        foreach ($metas as $meta) {
            $current = trim((string) $meta->meta_value);
            
            // Skip empty, zero, or '0' values (not parseable as dates)
            if (empty($current) || $current === '0' || $current === '0000-00-00') {
                continue;
            }
            
            // Extract only the date part if it contains time or other chars
            $timestamp = strtotime($current);
            if ($timestamp === false || $timestamp <= 0) {
                \Convoca\Core\Logger::warning("Upgrade 1.0.2: fecha inválida '{$current}' para post_id {$meta->post_id}, omitiendo.", 'Members/Upgrade');
                continue;
            }

            $normalized = wp_date('Y-m-d', $timestamp);
            if ($normalized !== $current) {
                update_post_meta($meta->post_id, '_bdv_fecha_renovacion', $normalized);
            }
        }
        
        return true;
    }
}
