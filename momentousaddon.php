<?php
/*
Plugin Name: Gravity Forms Momentous Feed Add-On
Plugin URI: https://www.shakewell.agency/
Description: An add-on for Momentous
Version: 1.8
Author: Shakewell
Author URI: https://www.shakewell.agency/
*/

define('GF_MOMENTOUS_FEED_ADDON_VERSION', '1.8');

add_action('gform_loaded', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'load' ), 5);
add_action('gform_after_submission', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'processSubmission' ), 5);
add_action('momentous_async_send_cron', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'do_async_send' ));
add_action('momentous_process_failed_requests_cron', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'process_async_failed_requests' ));

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
            accounts_call_status      varchar(10) null,
            opportunities_call_status varchar(10) null,
            status       varchar(10) not null,
            accounts_response    LONGTEXT    null,
            opportunities_response    LONGTEXT    null,
            created_at  TIMESTAMP   not null,
            executed_at TIMESTAMP   null,
            last_attempt_at TIMESTAMP null,
            completed_at TIMESTAMP null,
            constraint PRIMARY_ID_KEY
                primary key (id)
        ) $charset_collate;";
        dbDelta($sql);
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

    public static function processSubmission($form)
    {
        $object = gf_momentous_feed_addon();
        $mapping = $object->get_mapped_fields($form['form_id']);
        $values = $object->process_mapped_fields($mapping, $form);
        $object->process_request($values);
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