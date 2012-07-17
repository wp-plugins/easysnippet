<?php
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

if (!class_exists('EasyTemplate', false)) {
    require_once dirname(__FILE__) . '/lib/EasyTemplate.php';
}

class EasySnippet {
    const JQUERYJS = "https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js";
    const JQUERYUIJS = "https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js";
    const JQUERYUICSS = "http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/base/jquery-ui.css";
    const GOOGLEURL = 'http://www.google.com/webmasters/tools/richsnippets?url=%s';
    private $pluginsURL;
    private $pluginsDIR;
    private $thisPluginDIR;
    private $version = '1.1';
    
    function __construct() {
        
        /*
         * For convenience
        */
        $this->pluginsURL = WP_PLUGIN_URL;
        $this->pluginsDIR = WP_PLUGIN_DIR;
        $this->thisPluginDIR = dirname(__FILE__);
        $this->thisPluginURL = rtrim(plugin_dir_url(__FILE__), '/');
        
        add_action('admin_menu', array ($this, 'addMenu'));
        
        if ($GLOBALS["pagenow"] == 'admin-ajax.php' && (!isset($_REQUEST['action']) || ($_REQUEST['action'] != 'snippettest') && $_REQUEST['action'] != 'snippetget')) {
            return;
        }
        
        add_action('wp_ajax_snippettest', array ($this, 'snippetTest'));
        add_action('wp_ajax_snippetget', array ($this, 'getPosts'));
    
    }
    
    function enqueueScripts() {
        wp_enqueue_script('easysnippet', "$this->thisPluginURL/js/easysnippet.js", array ('jquery-ui-tabs', 'jquery-ui-button'), $this->version, true);
    }
    
    function enqueueStyles() {
        wp_enqueue_style("easysnippetUI", "$this->thisPluginURL/ui/easysnippetUI.css", array (), $this->version);
        wp_enqueue_style("easysnippet", "$this->thisPluginURL/css/easysnippet.css", array (), $this->version);
    }
    
    function addMenu() {
        $hook = add_menu_page('Sitewide Rich Snippet Report', 'Rich Snippets', 'manage_options', 'easysnippet', array ($this, 'run'), "$this->pluginsURL/easysnippet/images/snippet16.png");
        add_action("admin_print_scripts-$hook", array ($this, 'enqueueScripts'));
        add_action("admin_print_styles-$hook", array ($this, 'enqueueStyles'));
    }
    
    function run() {
        global $wpdb;
        $ajaxURL = admin_url('admin-ajax.php');
        
        $count = $wpdb->get_results("SELECT COUNT(*) AS c FROM $wpdb->posts WHERE post_status = 'publish'");
        $count = $count[0]->c;
        echo <<<EOD
        <script type="text/javascript">
        /* <![CDATA[ */
        if (typeof RICHSNIPPET == "undefined") {
            var RICHSNIPPET = {
            };
        }
        RICHSNIPPET.pluginsURL = '$this->pluginsURL';
        RICHSNIPPET.ajaxURL = '$ajaxURL';
        RICHSNIPPET.totalPosts= $count;
        /* ]]> */
        </script>
EOD;
        $data = new stdClass();
        $data->blogName = get_option("blogname");
        
        $template = new EasyTemplate("$this->thisPluginDIR/templates/easysnippet.html");
        $html = $template->replace($data);
        echo $html;
    
    }
    
    function getPosts() {
        global $wpdb;
        
        if (!current_user_can("administrator")) {
            echo "Not admin!";
            exit();
        }
        
        $last = $_POST['last'];
        $seq = $_POST['seq'] == 0 ? 'ASC' : 'DESC';
        $data = array ();
        $posts = $wpdb->get_results("SELECT ID, post_title FROM $wpdb->posts WHERE ID > $last AND post_status = 'publish' ORDER BY ID $seq LIMIT 5");
        foreach ($posts as $post) {
            $item = new stdClass();
            $item->ID = $post->ID;
            $item->postURL = get_permalink($post->ID);
            $item->postTitle = get_the_title($post->ID);
            $data[] = $item;
        }
        echo json_encode($data);
        exit();
    }
    
    function snippetTest() {
        $url = $_POST['url'];
        $id = $_POST['id'];
        $errno = 0;
        $errstr = '';
        $return = new stdClass();
        
        $url = sprintf(self::GOOGLEURL, urlencode($url));
        
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $args = array ('user-agent' => $ua);
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $return->html = "Could not connect to www.google.com";
            $return->id = $id;
            $return->status = -1;
            echo json_encode($return);
            exit();
        }
        
        $html = $response['body'];
        $return->html = substr($html, strpos($html, "<body"));
        $return->id = $id;
        $return->status = $response['response']['code'];
        echo json_encode($return);
        exit();
    }
    
    function easysnippetActivated() {
        // Hook for activation code
    }
    
    function easysnippetDeactivated() {
        // Hook for deactivation code
    }

}
