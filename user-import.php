<?php
/**
 * Plugin Name:       User Import
 * Description:       Handle the basics with this plugin.
 * Version:           1.10.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Sahil Gulati
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       user-import
 * Domain Path:       /languages
 */

function enqueue_admin_custom_script() {
    wp_enqueue_script( 'my_custom_script', plugin_dir_url( __FILE__ ) . 'js/myscript.js', array(), '1.0' );
    wp_enqueue_style('custom_css', plugin_dir_url( __FILE__ ) . 'css/custom-style.css');
}
add_action( 'admin_enqueue_scripts', 'enqueue_admin_custom_script' );

add_action('admin_menu', 'import_user_add_pages');

function import_user_add_pages() {
     add_menu_page(
        __( 'Import User', 'user-import' ),
        __( 'Import User','user-import' ),
        'manage_options',
        'import-user-page',
        'import_user_page_callback',
        ''
    );
}
 
/**
 * Disply callback.
 */
function import_user_page_callback() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

  //Get the active tab from the $_GET param
  $default_tab = null;
  $tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;

  ?>
  <!-- Our admin page content should all be inside .wrap -->
  <div class="wrap">
    <!-- Print the page title -->
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <!-- Here are our tabs -->
    <nav class="nav-tab-wrapper">
      <a href="?page=import-user-page" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">Import</a>
      <a href="?page=import-user-page&tab=history" class="nav-tab <?php if($tab==='history'):?>nav-tab-active<?php endif; ?>">History</a>
    </nav>

    <div class="tab-content">
    <?php switch($tab) :
      case 'history':
        echo 'History will be displayed here'; //Put your HTML here
        break;
      default:
        echo do_shortcode( '[import-code]' );
        break;
    endswitch; ?>
    </div>
  </div>
  <?php
}

/**
 * Shortcode.
 */
add_shortcode( 'import-code', 'import_code_func' );
function import_code_func( $atts ) {
    $html='';

    $html .= '<form class="csv-form" id="formcsv" method="POST" enctype="multipart/form-data">';

        $html .= '<p class="form-field">';
            $html .= '<input type="file" id="csv_file" name="csv_file">';
            $html .= '<input type="hidden" name="action" value="my_action">';
        $html .= '</p>';

        $html .= '<p class="form-field">';

            // Output the nonce field
            $html .= wp_nonce_field( 'upload_csv_file', 'csv_nonce', true, false );

            $html .= '<input type="submit" name="submit_csv_form" value="' . esc_html__( 'Import', 'user-import' ) . '">';
            $html .= '<div class="loader"></div>';
        $html .= '</p>';

    $html .= '</form>';

    return $html;
}

 if ( ! function_exists( 'handle_file_upload_csv' ) ) {

    /**
     * Handles the file upload request.
     */
    function handle_file_upload_csv() {
        // Stop immidiately if form is not submitted
        if ( ! isset( $_POST['submit_csv_form'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['csv_nonce'], 'upload_csv_file' ) ) {
            wp_die( esc_html__( 'Nonce mismatched', 'user-import' ) );
        }

        // Throws a message if no file is selected
        if ( ! $_FILES['csv_file']['name'] ) {
            wp_die( esc_html__( 'Please choose a file', 'user-import' ) );
        }

        $allowed_extensions = array( 'csv', 'xml' );
        $file_type = wp_check_filetype( $_FILES['csv_file']['name'] );
        $file_extension = $file_type['ext'];
        // var_dump($file_extension);
        // die();

        //Check for valid file extension
        if ( ! in_array( $file_extension, $allowed_extensions ) ) {
            wp_die( sprintf(  esc_html__( 'Invalid file extension, only allowed: %s', 'theme-text-domain' ), implode( ', ', $allowed_extensions ) ) );
        }

        // $file_size = $_FILES['wpcfu_file']['size'];
        // $allowed_file_size = 512000; // Here we are setting the file size limit to 500 KB = 500 × 1024

        // // Check for file size limit
        // if ( $file_size >= $allowed_file_size ) {
        //     wp_die( sprintf( esc_html__( 'File size limit exceeded, file size should be smaller than %d KB', 'theme-text-domain' ), $allowed_file_size / 1000 ) );
        // }

        // These files need to be included as dependencies when on the front end.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        // Let WordPress handle the upload.
        // Remember, 'wpcfu_file' is the name of our file input in our form above.
        // Here post_id is 0 because we are not going to attach the media to any post.
        $attachment_id = media_handle_upload( 'csv_file', 0 );

        // if ( is_wp_error( $attachment_id ) ) {
        //     // There was an error uploading the image.
        //     wp_die( $attachment_id->get_error_message() );
        // } else {
        //     // We will redirect the user to the attachment page after uploading the file successfully.
        //     wp_redirect( get_the_permalink( $attachment_id ) );
        //     exit;
        // }
        $file = wp_get_attachment_url($attachment_id);
 
        // Open the file for reading
        if (($h = fopen("{$file}", "r")) !== FALSE) :
         
            // Each line in the file is converted into an individual array that we call $data
            // The items of the array are comma separated
              while (($data = fgetcsv($h, 1000, ",")) !== FALSE) :
                        // Prevent duplication if username is already exists
                if ( !username_exists($data[3]) ) :
 
                    $new_user = array(
                        'user_pass'  => $data[6],
                        'user_login' => $data[3],
                        'user_email' => sanitize_email($data[4]),
                        'first_name' => sanitize_text_field($data[1]),
                        'last_name'  => sanitize_text_field($data[2]),
                        'user_url'   => esc_url_raw($data[5]),
                        'role'       => sanitize_text_field($data[7]),
                    );
 
                    wp_insert_user($new_user);
                    //echo "insert user";
                    //die();
 
                endif;
 
              endwhile;
 
              // Close the file
              fclose($h);
        endif;
    }
}

/**
 * Hook the function that handles the file upload request.
 */
//add_action( 'init', 'handle_file_upload_csv' );


add_action( 'wp_ajax_my_action', 'my_action' );

function my_action() {
    global $wpdb; // this is how you get access to the database

    $allowed_extensions = array( 'csv', 'xml' );
    $file_type = wp_check_filetype( $_FILES['csv_file']['name'] );
    $file_extension = $file_type['ext'];
    // var_dump($file_extension);
    // die();

    //Check for valid file extension
    if ( ! in_array( $file_extension, $allowed_extensions ) ) {
        wp_die( sprintf(  esc_html__( 'Invalid file extension, only allowed: %s', 'theme-text-domain' ), implode( ', ', $allowed_extensions ) ) );
    }
    // These files need to be included as dependencies when on the front end.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

    
        $attachment_id = media_handle_upload( 'csv_file', 0 );

        
        $file = wp_get_attachment_url($attachment_id);
 
        // Open the file for reading
        if (($h = fopen("{$file}", "r")) !== FALSE) :
         
            // Each line in the file is converted into an individual array that we call $data
            // The items of the array are comma separated
              while (($data = fgetcsv($h, 1000, ",")) !== FALSE) :
                        // Prevent duplication if username is already exists
                if ( !username_exists($data[3]) ) :
 
                    $new_user = array(
                        'user_pass'  => $data[6],
                        'user_login' => $data[3],
                        'user_email' => sanitize_email($data[4]),
                        'first_name' => sanitize_text_field($data[1]),
                        'last_name'  => sanitize_text_field($data[2]),
                        'user_url'   => esc_url_raw($data[5]),
                        'role'       => sanitize_text_field($data[7]),
                    );
 
                    wp_insert_user($new_user);
                    //echo "insert user";
                    //die();
 
                endif;
 
              endwhile;
 
              // Close the file
              fclose($h);
        endif;
        echo "done";
    wp_die(); // this is required to terminate immediately and return a proper response
}
