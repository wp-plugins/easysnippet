<?php
/*
Plugin Name: EasySnippet
Plugin URI: http://www.easy-plugins.com/
Description: Run a site-wide Google Rich Snippet Test
Author: Jayce53
Version: 1.1
Author URI: http://www.easy-plugins.com/
License: GPLv2 or later
*/

/*

Copyright (c) 2010-2012 Box Hill LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if (!function_exists('add_action')) {
    echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
    exit();
}

if (!function_exists('easysnippetPHP5')) {
    function easysnippetPHP5() {
        wp_die("Rich Snippet requires PHP 5+.  Your server is running PHP" . phpversion() . '<br /><a href="/wp-admin/plugins.php">Go back</a>');
    }
}

if (phpversion() < '5') {
    register_activation_hook(__FILE__, "easysnippetPHP5");
    return;
}

/*
 * Ignore anything we don't care about
 */
if ($GLOBALS["pagenow"] == "admin-ajax.php") {
    if (!isset($_REQUEST["action"]) || ($_REQUEST["action"] != "snippettest" && $_REQUEST["action"] != "snippetget")) {
        return;
    }
}

/*
* Instantiate the class IF we're in admin and it doesn't already exist
*/

if (is_admin() && !class_exists('EasySnippetSnippet', false)) {
    require_once 'class-easysnippet.php';
    $easysnippet = new EasySnippet();
    register_activation_hook(__FILE__, array ($easysnippet, "easysnippetActivated"));
    register_deactivation_hook(__FILE__, array ($easysnippet, "easysnippetDeactivated"));
}
?>
