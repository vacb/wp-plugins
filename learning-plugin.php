<?php

/*
  Plugin Name: Our Test Plugin
  Description: A plugin
  Version: 1.0
  Author: Victoria
  Author URI: https://victoriablackburn.uk
  Text Domain: wcpdomain
  Domain Path: /languages
*/

// Test plugin - adds text to the end of a post
// add_filter('the_content', 'addToEndOfPost');
// function addToEndOfPost($content) {
//   if(is_single() && is_main_query()) {
//     return $content . '<p>Hello</p>';
//   }
//   return $content;
// }

class WordCountAndTimePlugin {
  function __construct() {
    add_action('admin_menu', array($this, 'adminPage'));
    add_action('admin_init', array($this, 'settings'));
    add_filter('the_content', array($this, 'ifWrap'));
    add_action('init', array($this, 'languages'));
  }

  function languages() {
    // Args: $domain:string, $deprecated:string|false, $plugin_rel_path:string|false
    load_plugin_textdomain('wcpdomain', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }

  function ifWrap($content) {
    // If using default value, setting won't have an option in the database, so '1' is included for default
    // If all checkboxes unchecked, don't need to wrap
    if (is_main_query() AND is_single() AND 
      (
        get_option('wcp_wordcount', '1') OR 
        get_option('wcp_charcount', '1') OR 
        get_option('wcp_readtime', '1')
      )) {
        return $this->createHTML($content);
    }
    return $content;
  }

  function createHTML ($content) {
    $html = '<h3>' . esc_html(get_option('wcp_headline', 'Post Statistics')) . '</h3><p>';

    // Calculate the word count (needed for both wordcount and readtime)
    if (get_option('wcp_wordcount', '1') OR get_option('wcp_readtime', '1')) {
      $wordCount = str_word_count(strip_tags($content));
    }

    // Concat on word count if option requires it
    if (get_option('wcp_wordcount', '1')) {
      $html .= __('This post has', 'wcpdomain') . ' ' . $wordCount . ' '. __('words', 'wcpdomain') . '.<br>';
    }

    // Concat on character count if option requires it
    if (get_option('wcp_charcount', '1')) {
      $html .= __('This post has', 'wcpdomain') . ' ' . strlen(strip_tags($content)) . ' ' . __('characters', 'wcpdomain') . '.<br>';
    }

    // Concat on read time if option requires it
    // Uses average of 225 words per minute
    if (get_option('wcp_readtime', '1')) {
      $readtime = round($wordCount/225);
      $html .= 'This post will take about ' . $readtime . ($readtime != 1 ? ' minutes ' : ' minute ') . 'to read.<br>';
    }

    // Concat on closing p tag
    $html .= '</p>';

    // Deal with setting to decide if stats should be at the beginning or end of post
    if (get_option('wcp_location', '0') == '0') {
      return $html . $content;
    }
    return $content . $html;
  }
 
  // Register settings we want to create and change
  function settings() {
    // ADD SETTINGS SECTION
    // Args: name of section, subtitle of section (can be null), html content if needed e.g. description text, page slug to add section to
    add_settings_section('wcp_first_section', null, null, 'word-count-settings-page');

    // LOCATION SETTING
    // Args: name of setting to tie to, html label text i.e. name users will see, function to build html, page slug for settings page we're using, which section to add option in
    add_settings_field('wcp_location', 'Display Location', array($this, 'locationHTML'),'word-count-settings-page', 'wcp_first_section');
    // Args: group settings belong to, name of setting, array with sanitise callback and default value
    register_setting('wordcountplugin', 'wcp_location', array('sanitize_callback' => array($this, 'sanitizeLocation'), 'default' => '0'));

    // HEADLINE TEXT SETTING
    add_settings_field('wcp_headline', 'Headline Text', array($this, 'headlineHTML'),'word-count-settings-page', 'wcp_first_section');
    register_setting('wordcountplugin', 'wcp_headline', array('sanitize_callback' => 'sanitize_text_field', 'default' => 'Post Statistics'));

    // WORD COUNT DISPLAY SETTING
    // Extra param to pass name to HTML display function
    add_settings_field('wcp_wordcount', 'Word Count', array($this, 'checkboxHTML'),'word-count-settings-page', 'wcp_first_section', array('boxName' => 'wcp_wordcount'));
    register_setting('wordcountplugin', 'wcp_wordcount', array('sanitize_callback' => 'sanitize_text_field', 'default' => '1'));

    // CHARACTER COUNT DISPLAY SETTING
    add_settings_field('wcp_charcount', 'Character Count', array($this, 'checkboxHTML'),'word-count-settings-page', 'wcp_first_section', array('boxName' => 'wcp_charcount'));
    register_setting('wordcountplugin', 'wcp_charcount', array('sanitize_callback' => 'sanitize_text_field', 'default' => '1'));

    // READ TIME DISPLAY SETTING
    add_settings_field('wcp_readtime', 'Read Time', array($this, 'checkboxHTML'),'word-count-settings-page', 'wcp_first_section', array('boxName' => 'wcp_readtime'));
    register_setting('wordcountplugin', 'wcp_readtime', array('sanitize_callback' => 'sanitize_text_field', 'default' => '1'));
  }

  // Sanitize location input to ensure only a 0 or a 1 can be entered
  function sanitizeLocation($input) {
    if($input != '0' AND $input != '1') {
      add_settings_error('wcp_location', 'wcp_location_error', 'Display location must be either beginning or end.');
      return get_option('wcp_location');
    }
    return $input;
  }

  // CHECKBOX HTML FUNCTION
  function checkboxHTML($args) { ?>
    <input type="checkbox" name="<?php echo $args['boxName'] ?>" value="1" <?php checked(get_option($args['boxName']), '1') ?>>
  <?php }

  // HEADLINE HTML FUNCTION
  function headlineHTML() { ?>
    <input type="text" name="wcp_headline" value="<?php echo esc_attr(get_option('wcp_headline')) ?>">
  <?php }

  // LOCATION HTML FUNCTION
  function locationHTML() { ?>
    <select name="wcp_location">
      <!-- Use selected to set field to the option currently in the database (otherwise it will default to the first option) -->
      <option value="0" <?php selected(get_option('wcp_location'), '0') ?>>Beginning of post</option>
      <option value="1" <?php selected(get_option('wcp_location'), '1') ?>>End of post</option>
    </select>
  <?php }

  // Add a link into the WP settings menu
  function adminPage() {
    // Args: title of page to create/tab title, title used in settings menu, necessary permissions, slug for new page, function to output html content
    add_options_page('Word Count Settings', __('Word Count', 'wcpdomain'), 'manage_options', 'word-count-settings-page', array($this, 'ourHTML'));
  }
  
  function ourHTML() { ?>
    <div class="wrap">
      <h1>Word Count Settings</h1>
      <form action="options.php" method="POST">
        <?php
          // settings_fields() adds appropriate hidden html fields with action value, nonce value, does security and permissions aspect
          settings_fields('wordcountplugin');
          do_settings_sections('word-count-settings-page');
          submit_button();
        ?>
      </form>
    </div>
  <?php }
  
}

$wordCountAndTimePlugin = new WordCountAndTimePlugin();
