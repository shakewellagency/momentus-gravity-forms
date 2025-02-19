<?php
/*
Plugin Name: Gravity Forms Momentous Feed Add-On
Plugin URI: https://www.shakewell.agency/
Description: An add-on for Momentous
Version: 1.5
Author: Shakewell
Author URI: https://www.shakewell.agency/
*/

define('GF_MOMENTOUS_FEED_ADDON_VERSION', '1.5');

add_action('gform_loaded', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'load' ), 5);
add_action('gform_after_submission', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'processSubmission' ), 5);
add_action('momentous_async_send_cron', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'do_async_send' ));
add_action('momentous_process_failed_requests_cron', array( 'GF_Momentous_Feed_AddOn_Bootstrap', 'process_async_failed_requests' ));

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
            wp_schedule_event(time(), 'every_three_minutes', 'momentous_async_send_cron');
        }
        if (!wp_next_scheduled('momentous_process_failed_requests_cron')) {
            wp_schedule_event(time(), 'every_one_minute', 'momentous_process_failed_requests_cron');
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
    }

    public static function do_async_send()
    {
        $object = gf_momentous_feed_addon();
        $object->process_async_requests();
    }

    public static function process_async_failed_requests() {
        $object = gf_momentous_feed_addon();
        $object->process_failed_async_requests();
    }

    public static function cron_schedule_filter() {
        $schedules['every_three_minutes'] = [
            'interval' => 180, // 180 seconds = 3 minutes
            'display'  => __('Every 3 Minutes'),
        ];
        $schedules['every_one_minute'] = [
            'interval' => 120, // 60 seconds = 1 minute
            'display'  => __('Every 3 Minutes'),
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
