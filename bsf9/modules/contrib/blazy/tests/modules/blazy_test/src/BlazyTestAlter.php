<?php

namespace Drupal\blazy_test;

use Drupal\Core\Render\Element\RenderCallbackInterface;

/**
 * Provides a render callback to sets blazy_test related URL attributes.
 *
 * @see blazy_test_blazy_alter()
 * @see blazy_photoswipe_blazy_alter()
 *
 * @todo remove already taken care of at 2.6.
 */
class BlazyTestAlter implements RenderCallbackInterface {

  /**
   * The #pre_render callback: Sets lightbox image URL.
   */
  public static function preRender($image) {
    $settings = $image['#settings'];
    $blazies  = $settings['blazies'];
    $box_url  = $blazies->get('lightbox.url');

    // Video's HREF points to external site, adds URL to local image.
    if ($box_url && $blazies->get('media.embed_url')) {
      $image['#url_attributes']['data-box-url'] = $box_url;
    }

    return $image;
  }

}
