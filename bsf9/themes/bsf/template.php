<?php
/**
 * @file
 * The primary PHP file for this theme.
 */
 
/**
 * Variables preprocess function for the "page" theming hook.
 */
function bsf_preprocess_page(&$vars) {
  if (isset($vars['node'])) {
    $suggests = &$vars['theme_hook_suggestions'];
    $args = arg();
    unset($args[0]);		
    $type = "page__type_{$vars['node']->type}";
    $suggests = array_merge(
      $suggests,
      array($type),
      theme_get_suggestions($args, $type)
    );
		$vars['node_content'] =& $vars['page']['content']['system_main']['nodes'][arg(1)];
  } else if (arg(0) == 'taxonomy' && arg(1) == 'term') {
    $term = taxonomy_term_load(arg(2));
		$vocabulary = taxonomy_vocabulary_load($term->vid);
		$vars['theme_hook_suggestions'][] = 'page__taxonomy_vocabulary_' . $vocabulary->machine_name;
	}	
}

function bsf_file_link($variables) {
	$file = $variables['file'];
	$url = file_create_url($file->uri);
	
	$options = array();
	$options = array(
		'attributes' => array(
			'type' => $file->filemime . '; length=' . $file->filesize,
			'target' => '_blank',
		    'class' => 'btn btn-bsf',
		),
		'html' => TRUE,
	);
	
    if ($file->filename == 'conocenos.pdf'){ 
    	$options['attributes']['data-event-category'] = 'Conocenos';
    	$options['attributes']['data-event-action'] = 'Download';
    	$options['attributes']['data-event-label'] = 'PDF_Conocenos_ES';
	}elseif ($file->filename == 'calidad.pdf'){
	    $options['attributes']['data-event-category'] = 'Calidad';
	    $options['attributes']['data-event-action'] = 'Download';
	    $options['attributes']['data-event-label'] = 'PDF_Calidad_ES';	
	}else{
	 //	
	}
	
  if (empty($file->description)) {
    $link_text = $file->filename;
  } else {
    $link_text = $file->description;
    $options['attributes']['title'] = check_plain($file->filename);
	
  }	
	return l('<i class="fa fa-cloud-download" aria-hidden="true"></i>'.$link_text, $url, $options);
}


/**
 * Implements hook_page_alter().
 */
function bsf_page_alter(&$page) {
  // Get critical css file.
  $filename = FALSE;
  $current_path = current_path();
  $path = drupal_get_path('theme', $GLOBALS['theme']);
  if (!$filename && drupal_is_front_page() && is_readable("$path/critical-css/urls/front.css")) {
    $filename = "$path/critical-css/urls/front";
  }
  if (!$filename && is_readable("$path/critical-css/urls/{$current_path}.css")) {
    $filename = "$path/critical-css/urls/{$current_path}";
  }
  if (!$filename) {
    // By node type.
    $object = menu_get_object();
    if (isset($object->nid) && is_readable("$path/critical-css/node_type/{$object->type}.css")) {
      $filename = "$path/critical-css/node_type/{$object->type}";
    }
  }

  // Add inline critical css for front page.
  if (!empty($filename)) {
    $inline_css = advagg_load_stylesheet("$filename.css", TRUE);
    $page['content']['#attached']['css']["$filename.css"] = array(
      'data' => $inline_css,
      'type' => 'inline',
      'group' => CSS_SYSTEM - 1,
      'weight' => -50000,
      'movable' => FALSE,
      'critical-css' => TRUE,
    );
    // Add in domain prefetch.
    if (is_readable("$filename.dns")) {
      $domains = file("$filename.dns", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
      $domains = array_unique($domains);
      foreach ($domains as $domain) {
        advagg_add_dns_prefetch($domain);
      }
    }
  }
}
/**
 * Implements hook_file_url_alter().
 *
 * Make all URLs be protocol relative.
 * Note: protocol relatice URLs will cause IE7/8 to download stylesheets twice.
 */
function bsf_file_url_alter(&$url) {

  global $base_url;

  static $relative_base_url = NULL, $relative_base_length = NULL;

  $scheme = file_uri_scheme($url);

  // For some things (e.g., images) hook_file_url_alter can be called multiple
  // times. So, we have to be sure not to alter it multiple times. If we already
  // are relative protocol we can just return.
  // Only setup the and parse this stuff once.
  if (!$relative_base_url || !$relative_base_length) {
    $relative_base_url = '//' . file_uri_target($base_url);
    $relative_base_length = strlen($relative_base_url);
  }
  if (!$scheme && substr($url, 0, $relative_base_length) == $relative_base_url) {
    return;
  }

  // Handle the case where we have public files with the scheme public:// or
  // the case the relative path doesn't start with a /. Internal relative urls
  // have the base url prepended to them.
  if (!$scheme || $scheme == 'public') {

    // Internal Drupal paths.
    if (!$scheme) {
      $path = $url;
    }
    else {
      $wrapper = file_stream_wrapper_get_instance_by_scheme($scheme);
      $path = $wrapper->getDirectoryPath() . '/' . file_uri_target($url);
    }

    // Clean up Windows paths.
    $path = str_replace('\\', '/', $path);

    $url = $base_url . '/' . $path;
  }

  // Convert full URLs to relative protocol.
  $protocols = array('http', 'https');
  $scheme = file_uri_scheme($url);
  if ($scheme && in_array($scheme, $protocols)) {
    $url = '//' . file_uri_target($url);
  }
}