<?php
/*
Plugin Name: Gravity Forms Momentous Feed Add-On
Plugin URI: https://www.shakewell.agency/
Description: An add-on for Momentous
Version: 1.9
Author: Shakewell
Author URI: https://www.shakewell.agency/
*/

define('GF_MOMENTOUS_FEED_ADDON_VERSION', '1.9');

add_action('gform_loaded', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'load' ), 5);
add_action('gform_after_submission', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'processSubmission' ), 5, 2);
add_action('momentous_async_send_cron', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'do_async_send' ));
add_action('momentous_process_failed_requests_cron', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'process_async_failed_requests' ));
add_action('plugins_loaded', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'check_version_upgrade' ));

// Register watchdog CRON and its schedule
add_action('init', function () {
    if (!wp_next_scheduled('momentous_async_send_cron')) {
        wp_schedule_event(time(), 'every_one_minute', 'momentous_async_send_cron');
    }
    if (!wp_next_scheduled('momentous_process_failed_requests_cron')) {
        wp_schedule_event(time(), 'every_two_minutes', 'momentous_process_failed_requests_cron');
    }
    if (!wp_next_scheduled('momentous_cron_watchdog')) {
        wp_schedule_event(time(), 'every_ten_minutes', 'momentous_cron_watchdog');
    }
});
add_action('momentous_cron_watchdog', 'momentous_check_cron_health');

add_filter('cron_schedules', array('GF_Momentous_Feed_AddOn_Bootstrap','cron_schedule_filter'));

register_activation_hook(__FILE__, array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'activate_cron' ));
register_activation_hook(__FILE__, array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'create_table' ));
register_deactivation_hook(__FILE__, array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'delete_table' ));
register_deactivation_hook(__FILE__, array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'deactivate_cron' ));

class GF_Momentous_Feed_AddOn_Bootstrap
{
    const REQUEST_TABLE = 'momentous_requests';

    public static function load()
    {

        if (! method_exists('GFForms', 'include_feed_addon_framework')) {
            return;
        }
        require_once('class-gfmomentousfeedaddon.php');
        GFAddOn::register('GFMomentousFeedAddOn');
    }

    public static function create_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . self::REQUEST_TABLE;

        $sql = "CREATE TABLE " . $table_name . " (
            id         int auto_increment,
            body        LONGTEXT    not null,
            entry_id    int         null,
            form_id     int         null,
            accounts_call_status      varchar(10) null,
            opportunities_call_status varchar(10) null,
            status       varchar(10) not null,
            accounts_response    LONGTEXT    null,
            opportunities_response    LONGTEXT    null,
            retry_count int         DEFAULT 0 not null,
            created_at  TIMESTAMP   not null,
            executed_at TIMESTAMP   null,
            last_attempt_at TIMESTAMP null,
            completed_at TIMESTAMP null,
            constraint PRIMARY_ID_KEY
                primary key (id)
        ) $charset_collate;";
        dbDelta($sql);

        // Run migration for existing installations
        self::migrate_table();
    }

    public static function check_version_upgrade()
    {
        $saved_version = get_option('gf_momentous_version', '0');
        if (version_compare($saved_version, GF_MOMENTOUS_FEED_ADDON_VERSION, '<')) {
            self::migrate_table();
            update_option('gf_momentous_version', GF_MOMENTOUS_FEED_ADDON_VERSION);
        }
    }

    public static function migrate_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;

        // Check if entry_id column exists
        $column = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'entry_id'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `entry_id` INT NULL AFTER `body`");
        }

        // Check if form_id column exists
        $column = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'form_id'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `form_id` INT NULL AFTER `entry_id`");
        }

        // Check if retry_count column exists
        $column = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'retry_count'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `retry_count` INT DEFAULT 0 NOT NULL AFTER `opportunities_response`");

            // After adding retry_count column, clean up old failed records
            self::cleanup_old_failed_records();
        }
    }

    /**
     * Clean up old failed records during migration
     * Abandons failed records older than 7 days to prevent retrying ancient requests
     */
    public static function cleanup_old_failed_records()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;

        // Abandon failed records older than 7 days
        $abandoned_count = $wpdb->query(
            "UPDATE {$table_name}
            SET status = 'abandoned', retry_count = 5
            WHERE status = 'failed'
              AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        if ($abandoned_count > 0) {
            error_log("Momentous Migration: Abandoned {$abandoned_count} old failed records (>7 days)");
        }
    }

    public static function delete_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::REQUEST_TABLE;
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }


    public static function activate_cron()
    {
        if (!wp_next_scheduled('momentous_async_send_cron')) {
            wp_schedule_event(time(), 'every_one_minute', 'momentous_async_send_cron');
        }
        if (!wp_next_scheduled('momentous_process_failed_requests_cron')) {
            wp_schedule_event(time(), 'every_two_minutes', 'momentous_process_failed_requests_cron');
        }
        if (!wp_next_scheduled('momentous_cron_watchdog')) {
            wp_schedule_event(time(), 'every_ten_minutes', 'momentous_cron_watchdog');
        }
    }

    public static function deactivate_cron()
    {
        $timestamp = wp_next_scheduled('momentous_async_send_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'momentous_async_send_cron');
        }

        $timestamp = wp_next_scheduled('momentous_process_failed_requests_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'momentous_process_failed_requests_cron');
        }

        $timestamp = wp_next_scheduled('momentous_cron_watchdog');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'momentous_cron_watchdog');
        }
    }

    public static function do_async_send()
    {
        update_option('momentous_last_async_cron', time());
        $object = gf_momentous_feed_addon();
        $object->process_async_requests();
    }

    public static function process_async_failed_requests() {
        update_option('momentous_last_failed_cron', time());
        $object = gf_momentous_feed_addon();
        $object->process_failed_async_requests();
    }

    public static function cron_schedule_filter() {
        $schedules['every_two_minutes'] = [
            'interval' => 120, // 180 seconds = 3 minutes
            'display'  => __('Every minute'),
        ];
        $schedules['every_one_minute'] = [
            'interval' => 60, // 60 seconds = 1 minute
            'display'  => __('Every minute'),
        ];
        $schedules['every_ten_minutes'] = [
            'interval' => 600,
            'display'  => __('Every 10 Minutes')
        ];
        return $schedules;
    }

    public static function processSubmission($entry, $form)
    {
        $object = gf_momentous_feed_addon();
        $entry_id = isset($entry['id']) ? $entry['id'] : null;
        $form_id = isset($form['id']) ? $form['id'] : null;

        $mapping = $object->get_mapped_fields($form_id, $entry_id);
        $values = $object->process_mapped_fields($mapping, $form, $entry_id, $form_id);
        $object->process_request($values, $entry_id, $form_id);
    }
}

function gf_momentous_feed_addon()
{
    return GFMomentousFeedAddOn::get_instance();
}

// Watchdog function (runs every 10 min to check CRON health)
function momentous_check_cron_health()
{
    $async_time  = get_option('momentous_last_async_cron');
    $failed_time = get_option('momentous_last_failed_cron');
    $now         = time();
    $threshold   = 10 * 60; // 10 minutes
    $problems    = [];

    if (!$async_time || ($now - $async_time) > $threshold) {
        $problems[] = 'momentous_async_send_cron has not run in the last 10 minutes.';
    }

    if (!$failed_time || ($now - $failed_time) > $threshold) {
        $problems[] = 'momentous_process_failed_requests_cron has not run in the last 10 minutes.';
    }

    if (!empty($problems)) {
        $message = "<p><strong>Momentous CRON Failure Watchdog Alert</strong></p>";
        $message .= '<ul><li>' . implode('</li><li>', $problems) . '</li></ul>';
        $message .= "<p>Time checked: " . current_time('mysql') . "</p>";

        $settings = gf_momentous_feed_addon()->get_plugin_settings();
        $to = !empty($settings['email_cron_failure_alerts']) ? $settings['email_cron_failure_alerts'] : get_option('admin_email');
        
        $subject = 'Momentous CRON Failure Detected';

        wp_mail($to, $subject, $message, ['Content-Type: text/html']);
    }
}