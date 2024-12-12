<?php
/*
Plugin Name: Gravity Forms Momentous Add-On
Description: Integrates Gravity Forms with Momentous
Version: 1.0.0
Author: Shakewell
Author URI: https://gravityforms.com
License: GPL-3.0+

------------------------------------------------------------------------
@author Shakewell Agency
@copyright Shakewell (c) Shakewell (https://www.shakewell.agency/)
*/

defined( 'ABSPATH' ) || die();

// Defines the current version of the Gravity Forms ConvertKit Add-On.
define( 'GF_MOMENTOUS_VERSION', '1.0.0' );

// Defines the minimum version of Gravity Forms required to run Gravity Forms Kit Add-On.
define( 'GF_MOMENTOUS_MIN_GF_VERSION', '2.0' );

// After Gravity Forms is loaded, load the Add-On.
add_action( 'gform_loaded', array( 'GF_Momentous_Bootstrap', 'load' ), 5 );

/**
 * Loads the Gravity Forms Kit Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0
 */
class GF_Momentous_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since  1.0
	 */
	public static function load() {

		// Requires the class file.
		require_once plugin_dir_path( __FILE__ ) . '/class-gf-momentous.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'GF_Momentous' );
	}

}

/**
 * Returns an instance of the GF_ConvertKit class
 *
 * @since  1.0
 *
 * @return GF_ConvertKit|bool An instance of the GF_ConvertKit class
 */
function gf_momentous() {
	return class_exists( 'GF_Momentous' ) ? GF_Momentous::get_instance() : false;
}
