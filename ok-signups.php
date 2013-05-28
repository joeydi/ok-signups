<?php
/*
Plugin Name: OK Signups
Plugin URI: http://okaypl.us/
Description: A simple plugin for saving and exporting contact info
Version: 0.0.1
Author: Joe di Stefano
Author URI: http://okaypl.us
License: GPL2
*/
/*  Copyright 2013  Joe di Stefano  (email : joeydi@okaypl.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $hook_suffix;

if ( !class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

require_once(ABSPATH . 'wp-admin/includes/template.php' );

if( ! class_exists('WP_Screen') ) {
    require_once( ABSPATH . 'wp-admin/includes/screen.php' );
}

class OK_Signups_List_Table extends WP_List_Table {

    function __construct() {
        global $status, $page;

        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'signup',     //singular name of the listed records
            'plural'    => 'signups',    //plural name of the listed records
            'ajax'      => false         //does this table support ajax?
        ) );
        
    }

    function column_default($item, $column_name) {
        return $item->$column_name;
    }

    function column_date($item) {
        return date( 'F j, Y, g:i a', strtotime( $item->date ) );
    }

    function get_columns() {
        $columns = array(
            'first_name' => 'First Name',
            'last_name'  => 'Last Name',
            'email'      => 'Email',
            'address'    => 'Address',
            'city'       => 'City',
            'state'      => 'State',
            'zip'        => 'Zip Code',
            'date'       => 'Date',
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'first_name' => array('first_name', false),
            'last_name'  => array('last_name', false),
            'email'      => array('email', false),
            'address'    => array('address', false),
            'city'       => array('city', false),
            'state'      => array('state', false),
            'zip'        => array('zip', false),
            'date'       => array('date', true),
        );
        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'export_signups' => 'Export'
        );
        return $actions;
    }

    function prepare_items() {
        global $wpdb;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        /* -- Preparing your query -- */
        $query = "SELECT * FROM $wpdb->signups";

        /* -- Ordering parameters -- */
        $orderby = !empty( $_GET["orderby"] ) ? mysql_real_escape_string( $_GET["orderby"] ) : 'date';
        $order = !empty( $_GET["order"] ) ? mysql_real_escape_string( $_GET["order"] ) : 'DESC';
        if ( !empty( $orderby ) & !empty( $order ) ) {
            $query .= ' ORDER BY ' . $orderby . ' ' . $order;
        }

        /* -- Pagination parameters -- */
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->query( $query );

        //adjust the query to take pagination into account
        if ( !empty( $current_page ) && !empty( $per_page ) ) {
            $offset = ($current_page - 1) * $per_page;
            $query .= ' LIMIT ' . (int)$offset . ',' . (int)$per_page;
        }

        $current_page = $this->get_pagenum();

        $this->items = $wpdb->get_results($query);

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ) );
    }
    
}


function ok_process_bulk_action() {

    // Create an instance of our package class...
    $signupsListTable = new OK_Signups_List_Table();

    // Use it to fetch the current bulk action
    $action = $signupsListTable->current_action();

    // Detect when a bulk action is being triggered...
    if ( $action && 'export_signups' === $action ) {
        global $wpdb;

        $query = "SELECT * FROM $wpdb->signups";
        $data = $wpdb->get_results( $query, ARRAY_N );

        require_once( __DIR__ . '/lib/parsecsv/parsecsv.lib.php' );
        $csv = new parseCSV();
        $csv->output( 'signups.csv', $data );
        die();
    }
}
add_action( 'plugins_loaded', 'ok_process_bulk_action' );


function ok_add_menu_items() {
    add_menu_page( 'Signup Contact Info', 'Signups', 'activate_plugins', 'ok_signups', 'ok_render_list_page');
}
add_action('admin_menu', 'ok_add_menu_items');


function ok_render_list_page() {

    //Create an instance of our package class...
    $signupsListTable = new OK_Signups_List_Table();

    //Fetch, prepare, sort, and filter our data...
    $signupsListTable->prepare_items();

    ?>
    <div class="wrap">

        <div id="icon-users" class="icon32"><br/></div>
        <h2>Signups Contact Info</h2>

        <form id="signups-filter" method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
            <?php $signupsListTable->display(); ?>
        </form>
        
    </div>
    <?php
}


function ok_signups_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'signups';

    $sql = "CREATE TABLE $table_name (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    first_name varchar(255) DEFAULT NULL,
    last_name varchar(255) DEFAULT NULL,
    email varchar(255) DEFAULT NULL,
    address varchar(255) DEFAULT NULL,
    city varchar(255) DEFAULT NULL,
    state varchar(2) DEFAULT NULL,
    zip varchar(10) DEFAULT NULL,
    date datetime DEFAULT NULL,
    PRIMARY KEY  (id)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'ok_signups_install' );


function ok_signup() {
    global $wpdb;

    $first_name = ( isset( $_POST['first_name'] ) && !empty( $_POST['first_name'] ) ) ? $_POST['first_name'] : '';
    $last_name = ( isset( $_POST['last_name'] ) && !empty( $_POST['last_name'] ) ) ? $_POST['last_name'] : '';
    $email = ( isset( $_POST['email'] ) && !empty( $_POST['email'] ) ) ? $_POST['email'] : '';
    $address = ( isset( $_POST['address'] ) && !empty( $_POST['address'] ) ) ? $_POST['address'] : '';
    $city = ( isset( $_POST['city'] ) && !empty( $_POST['city'] ) ) ? $_POST['city'] : '';
    $state = ( isset( $_POST['state'] ) && !empty( $_POST['state'] ) ) ? $_POST['state'] : '';
    $zip = ( isset( $_POST['zip'] ) && !empty( $_POST['zip'] ) ) ? $_POST['zip'] : '';

    $signup = $wpdb->insert( $wpdb->prefix . 'signups', array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'zip' => $zip,
        'date' => current_time('mysql', 1),
    ));

    echo $signup;
    die();
}
add_action( 'wp_ajax_signup', 'ok_signup' );
add_action( 'wp_ajax_nopriv_signup', 'ok_signup' );

$wpdb->signups = $wpdb->prefix . 'signups';
