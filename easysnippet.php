<?php
/*
Plugin Name: EasySnippet
Plugin URI: http://www.easy-plugins.com/
Description: Run a site-wide Google Rich Snippet Test
Author: Jayce53
Version: 2.0.0105
Author URI: http://www.easy-plugins.com/
License: GPLv2 or later
*/


/**
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


if (!class_exists('EasySnippet', false)) {

    /**
     * We only need load on admin pages and ajax requests that are specifically for us
     */
    global $pagenow;
    if (isset($pagenow)) {
        if ($pagenow == 'admin-ajax.php') {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            if (stripos($action, 'easysnippet') === false) {
                return;
            }
        } else if (!is_admin()) {
            return;
        }
    }

    if (phpversion() < '5') {
        if (!function_exists('EasySnippetPHP5')) {
            function EasySnippetPHP5() {
                wp_die("This plugin requires PHP 5+.  Your server is running PHP" . phpversion() . '<br /><a href="/wp-admin/plugins.php">Go back</a>');
            }
        }
        register_activation_hook(__FILE__, "EasySnippetPHP5");
        return;
    }

    /**
     * Autoload any of this plugin's classes
     *
     * @param $class
     */
    function EasySnippetAutoload($class) {
        if (strpos($class, 'EasySnippet') === 0) {
            /** @noinspection PhpIncludeInspection */
            @include (dirname(__FILE__) . "/lib/$class.php");
        }
    }

    spl_autoload_register("EasySnippetAutoload");

    $EasySnippet = new EasySnippet(dirname(__FILE__), WP_PLUGIN_URL . "/easysnippet");
}

