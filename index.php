<?php
/**
 * Plugin Name: Google Drive Race Results
 * Plugin URI: http://wabson.org/
 * Description: Display race entries and results stored in Google Drive
 * Version: 0.1
 * Author: Will Abson
 * Author URI: http://wabson.org/
 * License: Apache
 */

include_once __DIR__ . '/drive.php';

function results_html($attrs) {
    extract(shortcode_atts(array(
        'id' => '',
    ), $attrs));
    echo get_drive_html($id);
}

function remove_tags_by_name($doc, $tag_name) {
    $script = $doc->getElementsByTagName($tag_name);
    $remove = [];
    foreach($script as $item)
    {
        $remove[] = $item;
    }
    foreach ($remove as $item)
    {
        $item->parentNode->removeChild($item); 
    }
}

add_shortcode('drive_results', 'results_html');

function drive_plugin_menu() {
	add_options_page('Drive Results Settings', 'Drive Results', 'manage_options', 'drive-results', 'drive_plugin_options');
        add_action('admin_init', 'register_drive_settings');
}

function drive_plugin_options() {
    if (!current_user_can('manage_options')) {
        wp_die(__( 'You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
    <h1>Drive Results Settings</h1>
    <form method="post" action="options.php" enctype="multipart/form-data">
    <?php
        settings_fields('drive-results-settings-group');
        do_settings_sections('drive-results');
        submit_button();
    ?>
    </form>
    </div>
<?php
}

function register_drive_settings() {
    add_settings_section('drive-results-settings-group', null, null, 'drive-results');
    add_settings_field('credentials-file', 'Credentials File', 'credentials_file_display', 'drive-results', 'drive-results-settings-group');
    //register our settings
    register_setting('drive-results-settings-group', 'credentials-file', 'handle_file_upload');
}

function credentials_file_display() {
    ?>
        <input type="file" name="credentials-file" /> 
        <?php echo get_option('credentials-file'); ?>
    <?php
}

function handle_file_upload($option)
{
    error_log('Called function with option '.$option);
    $field_name = 'credentials-file';
    //if (!empty($_FILES[$field_name]['tmp_name']))
    error_log(print_r($_FILES, true));
    if (!empty($_FILES[$field_name]))
    {
        $upload_result = wp_handle_upload($_FILES[$field_name], array('test_form' => FALSE));
        if ($upload_result && !isset($upload_result['error'])) {
            error_log('File is valid, and was successfully uploaded.');
            error_log(print_r($upload_result), true);
            return $upload_result['file'];
        } else {
            error_log('File could not be uploaded, error: '.$upload_result['error']);
            return '';
        }
    }
 
    return $option;
}

function json_mime_types($mime_types){
    $mime_types['json'] = 'application/json';
    return $mime_types;
}
add_filter('upload_mimes', 'json_mime_types', 1, 1);

function create_post_types() {
  register_post_type('drive_resource',
    array(
      'labels' => array(
        'name' => __('Drive Resources'),
        'singular_name' => __('Drive Resource')
      ),
      'public' => true,
      'exclude_from_search'=> true,
      'publicly_queryable' => true,
      'has_archive' => true,
      'supports' => array('title', 'custom-fields'),
      'rewrite' => array('slug' => 'race'),
    )
  );
  register_post_type('race_sheets',
    array(
      'labels' => array(
        'name' => __('Race Sheets'),
        'singular_name' => __('Race Sheets')
      ),
      'public' => true,
      'exclude_from_search'=> true,
      'publicly_queryable' => true,
      'has_archive' => true,
      'supports' => array('title', 'custom-fields'),
      'rewrite' => array('slug' => 'race-sheet'),
    )
  );
}

function drive_resource_columns($cols) {
  $cols = array(
    'cb'            => '<input type="checkbox" />',
    'name'          => __( 'Name',          'trans' ),
    'id'            => __( 'Drive ID',      'trans' ),
    'resource_type' => __( 'Resource Type', 'trans' ),
  );
  return $cols;
}

function race_sheets_columns($cols) {
  $cols = array(
    'cb'            => '<input type="checkbox" />',
    'name'          => __( 'Name',          'trans' ),
    'id'            => __( 'Spreadsheet ID',      'trans' )
  );
  return $cols;
}

function drive_resource_column_values($column, $post_id) {
  switch ($column) {
    case 'id':
      $drive_id = get_post_meta( $post_id, 'resource_id', true);
      echo '<a href="https://drive.google.com/file/d/' . $drive_id . '/view">' . $drive_id. '</a>';
      break;
    case 'name':
      echo get_the_title($post_id);
      break;
    case 'resource_type':
      echo get_post_meta($post_id, 'resource_type', true);
      break;
  }
}

function race_sheets_column_values($column, $post_id) {
  switch ($column) {
    case 'id':
      $drive_id = get_post_meta( $post_id, 'spreadsheet_id', true);
      printf('<a href="https://docs.google.com/spreadsheets/d/%1$s/edit">%1$s</a>', $drive_id);
      break;
    case 'name':
      echo get_the_title($post_id);
      break;
  }
}

function drive_resource_templates($template) {
    $template_mappings = array( 'drive_resource' => 'drive-resource', 'race_sheets' => 'race-sheets' );
    foreach($template_mappings as $type_name => $tmpl_name) {
        $post_types = array($type_name);
        if (is_post_type_archive($post_types) && !file_exists(get_stylesheet_directory() . '/archive-' . $tmpl_name . '.php')) {
            $template = plugin_dir_path(__FILE__) .  'archive-' . $tmpl_name . '.php';
        }
        if (is_singular($post_types) && ! file_exists(get_stylesheet_directory() . '/single-' . $tmpl_name . '.php')) {
            $template = plugin_dir_path(__FILE__) .  'single-' . $tmpl_name . '.php';
        }
    }
    return $template;
}

function get_spreadsheet_last_modified() {
    $post_id = $_REQUEST['post_id'];
    $spreadsheet_id = get_post_meta($post_id, 'spreadsheet_id', true);
    $file = get_drive_file($spreadsheet_id);
    printf('{"name": "%s", "modifiedTime": "%s"}', $file->getName(), $file->getModifiedTime());
    wp_die();
}

add_action('wp_ajax_race_sheets_last_modified', 'get_spreadsheet_last_modified');
add_action('wp_ajax_nopriv_race_sheets_last_modified', 'get_spreadsheet_last_modified');

add_action('manage_drive_resource_posts_custom_column', 'drive_resource_column_values', 10, 2);
add_action('manage_race_sheets_posts_custom_column', 'race_sheets_column_values', 10, 2);

add_action('init', 'create_post_types');
add_action('admin_menu', 'drive_plugin_menu');

add_filter('manage_drive_resource_posts_columns', 'drive_resource_columns');
add_filter('manage_race_sheets_posts_columns', 'race_sheets_columns');

add_filter('template_include', 'drive_resource_templates');

?>
