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


class EasySnippet {
    const GOOGLEURL = 'http://www.google.com/webmasters/tools/richsnippets?url=%s';

    public static $EasySnippetDir;
    public static $EasySnippetURL;

    private $myVersion = '2.0.0105';

    private $pluginDir;
    private $pluginURL;

    function __construct($pluginDir, $pluginURL) {


        self::$EasySnippetDir = $pluginDir;
        self::$EasySnippetURL = $pluginURL;

        /*
         * For convenience
         */
        $this->pluginDir = $pluginDir;
        $this->pluginURL = $pluginURL;

        add_action('plugins_loaded', array($this, 'pluginsLoaded'));
    }

    function pluginsLoaded() {
        add_action('admin_menu', array($this, 'addMenu'));

        add_action('wp_ajax_easysnippetTest', array($this, 'snippetTest'));
        add_action('wp_ajax_easysnippetGet', array($this, 'getPosts'));

    }

    function enqueueScripts() {
        wp_enqueue_script('easysnippet', "$this->pluginURL/js/easysnippet.js", array('jquery-ui-tabs', 'jquery-ui-button'), $this->myVersion, true);
    }

    function enqueueStyles() {
        wp_enqueue_style("easysnippetUI", "$this->pluginURL/ui/easysnippetUI.css", array(), $this->myVersion);
        wp_enqueue_style("easysnippet", "$this->pluginURL/css/easysnippet.css", array(), $this->myVersion);
    }

    function addMenu() {
        $hook = add_menu_page('Sitewide Rich Snippet Report', 'Rich Snippets', 'manage_options', 'easysnippet', array($this, 'run'), "$this->pluginURL/images/snippet16.png");
        add_action("admin_print_scripts-$hook", array($this, 'enqueueScripts'));
        add_action("admin_print_styles-$hook", array($this, 'enqueueStyles'));
    }

    function run() {
        /** @var wpdb $wpdb */
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
        RICHSNIPPET.pluginsURL = '$this->pluginURL';
        RICHSNIPPET.ajaxURL = '$ajaxURL';
        RICHSNIPPET.totalPosts= $count;
        /* ]]> */
        </script>
EOD;
        $data = new stdClass();
        $data->blogName = get_option("blogname");

        $template = new EasySnippetTemplate("$this->pluginDir/templates/easysnippet.html");
        $html = $template->replace($data);
        echo $html;

    }

    function getPosts() {
        /** @var wpdb $wpdb */
        global $wpdb;

        if (!current_user_can("administrator")) {
            echo "Not admin!";
            exit();
        }

        $last = $_POST['last'];
        if ($_POST['seq'] == 0) {
            $q = "SELECT ID, post_title FROM $wpdb->posts WHERE ID > $last AND post_status = 'publish' ORDER BY ID ASC LIMIT 5";
        } else {
            $q = "SELECT ID, post_title FROM $wpdb->posts WHERE ID < $last AND post_status = 'publish' ORDER BY ID DESC LIMIT 5";
        }
        $seq = $_POST['seq'] == 0 ? 'ASC' : 'DESC';
        $data = array();
        $posts = $wpdb->get_results($q);
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
        $return = new stdClass();

        $url = sprintf(self::GOOGLEURL, urlencode($url));

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $args = array('user-agent' => $ua);
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

}

