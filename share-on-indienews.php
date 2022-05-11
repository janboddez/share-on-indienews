<?php
/**
 * Plugin Name: Share on IndieNews
 * Description: Adds an "IndieNews" syndication target.
 * Author:      Jan Boddez
 * Author URI:  https://jan.boddez.net/
 * License: GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: share-on-indienews
 * Version:     0.1.1
 *
 * @author  Jan Boddez <jan@janboddez.be>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 * @package Share_On_IndieNews
 */

namespace Share_On_IndieNews;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require dirname( __FILE__ ) . '/includes/class-post-handler.php';
require dirname( __FILE__ ) . '/includes/class-share-on-indienews.php';

Share_On_IndieNews::get_instance()->register();
