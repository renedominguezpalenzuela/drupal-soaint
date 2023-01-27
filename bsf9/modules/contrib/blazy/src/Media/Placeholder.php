<?php

namespace Drupal\blazy\Media;

/**
 * Provides placeholder thumbnail image.
 *
 * @todo recap similiraties and make them plugins.
 */
class Placeholder {

  /**
   * Defines constant placeholder Data URI image.
   *
   * <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>
   */
  const DATA = 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D"http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg"%20viewBox%3D"0%200%201%201"%2F%3E';

  /**
   * Build out the blur image.
   *
   * Provides image effect if so configured unless being sandboxed.
   * Being a separated .b-blur with .b-lazy, this should work for any lazy.
   * Ensures at least a hook_alter is always respected. This still allows
   * Blur and hook_alter for Views rewrite issues, unless global UI is set
   * which was already warned about anyway.
   *
   * Since 2.10, using client-size solution, too many bytes for a short life.
   */
  public static function blur(array &$variables, array &$settings) {
    $attributes = &$variables['attributes'];
    $blazies = $settings['blazies'];
    $uri = $blazies->get('blur.uri');
    $url = $blazies->get('blur.url');

    if (!$url) {
      return;
    }

    // Suppress useless warning of likely failing initial image generation.
    // Better than checking file exists.
    $mime = @mime_content_type($uri);
    $id = md5($url);
    $client = $blazies->get('ui.blur_client');
    $store = $client ? ($blazies->get('ui.blur_storage') ? 1 : 0) : -1;
    $blur = [
      '#theme' => 'image',
      '#uri' => $blazies->get('placeholder.url'),
      '#attributes' => [
        'class' => ['b-blur'],
        'data-b-blur' => "$store::$id::$mime::$url",
        'decoding' => 'async',
      ],
    ];

    // Preserves old behaviors.
    if (!$client) {
      $blur['#attributes']['class'][] = 'b-lazy';
      $blur['#attributes']['data-src'] = $blazies->get('blur.data');
    }

    $width = (int) ($settings['width'] ?? 0);
    if ($width > 980) {
      $attributes['class'][] = 'media--fx-lg';
    }

    // Reset as already stored.
    $blazies->set('blur.data', '');
    $variables['preface']['blur'] = $blur;
  }

  /**
   * Generates an SVG Placeholder.
   *
   * @param string $width
   *   The image width.
   * @param string $height
   *   The image height.
   *
   * @return string
   *   Returns a string containing an SVG.
   */
  public static function generate($width, $height): string {
    $width = $width ?: 100;
    $height = $height ?: 100;
    return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\'%20viewBox%3D\'0%200%20' . $width . '%20' . $height . '\'%2F%3E';
  }

  /**
   * Build thumbnails, also to provide placeholder for blur effect.
   *
   * Requires image style and dimensions setup after BlazyImage::prepare().
   * The `[data-thumb]` attribute usages:
   * - Zoom-in-out effect as seen at Splidebox and PhotoSwipe.
   * - Hoverable or static grid pagination/ thumbnails seen at Splide/ Slick.
   * - Lightbox thumbnails seen at Photobox.
   * - Switchable thumbnail to main stage seen at ElevateZoomPlus.
   * - Slider arrows with thumbnails as navigation previews, etc. seen at Slick.
   * - etc.
   *
   * The `[data-animation]` attribute usages:
   * - Blur animation.
   * - Any animation supported by `animate.css` as seen GridStack, or custom.
   *   Check out `/admin/help/blazy_ui` for details.
   *
   * Most of these had been implemented since 1.x.
   *
   * @see \Drupal\blazy\Blazy:prepared()
   * @see self:blurs()
   * @see self:thumbnails()
   */
  public static function prepare(array &$attributes, array &$settings) {
    // Requires dimensions and image style setup.
    self::blurs($settings);
    self::thumbnails($settings);

    // Apply attributes related to Blur and Thumbnail image style.
    $blazies = $settings['blazies'];
    if ($tn_url = $blazies->get('thumbnail.url')) {
      $attributes['data-thumb'] = $tn_url;
    }

    // Provides image effect if so configured unless being sandboxed.
    // Slick/ Splide lazy loads won't work, needs Blazy to make animation.
    if ($blazies->is('blazy') && $fx = $blazies->get('fx')) {
      $attributes['class'][] = 'media--fx';
      $attributes['data-animation'] = $fx;
    }
  }

  /**
   * Checks for blur settings, required Image style and dimensions setup.
   */
  private static function blurs(array &$settings): void {
    $blazies = $settings['blazies'];
    if (!$blazies->is('blur')) {
      return;
    }

    // Disable Blur if the image style width is less than Blur min-width.
    if ($minwidth = (int) $blazies->get('ui.blur_minwidth', 0)) {
      $width = (int) $blazies->get('image.width');
      if ($width < $minwidth) {
        // Ensures ony if Blur since animation can be anything.
        if ($blazies->get('fx') == 'blur') {
          $blazies->set('fx', NULL);
        }

        $blazies->set('is.blur', FALSE);
      }
    }
  }

  /**
   * Provide `data:image` placeholder for blur effect.
   *
   * Ensures at least a hook_alter is always respected. This still allows
   * Blur and hook_alter for Views rewrite issues, unless global UI is set
   * which was already warned about anyway.
   */
  private static function dataImage(&$blazies, $uri, $tn_uri, $tn_url, $style): void {
    if (!$blazies->is('blazy') || !$blazies->is('blur')) {
      return;
    }

    // Provides default path, in case required by global, but not provided.
    $style = $style ?: \blazy()->entityLoad('thumbnail', 'image_style');
    if (empty($tn_uri) && $style && BlazyFile::isValidUri($uri)) {
      $tn_uri = $style->buildUri($uri);
      $tn_url = BlazyFile::transformRelative($uri, $style);
    }

    // Overrides placeholder with data URI based on configured thumbnail.
    $valid = self::derivative($blazies, $uri, $tn_uri, $style, 'blur');
    if ($valid) {
      // Use client-side for better diet.
      if (!$blazies->get('ui.blur_client')
        && $content = file_get_contents($tn_uri)) {
        $blur = 'data:image/' .
          pathinfo($tn_uri, PATHINFO_EXTENSION) .
          ';base64,' .
          base64_encode($content);

        $blazies->set('blur.data', $blur);
      }

      $blazies->set('blur.uri', $tn_uri);
      $blazies->set('blur.url', $tn_url);

      // Prevents double animations.
      $blazies->set('use.loader', FALSE);
    }
  }

  /**
   * Ensures the thumbnail exists before creating a dataURI.
   */
  private static function derivative(&$blazies, $uri, $tn_uri, $style, $key = 'blur'): bool {
    if (BlazyFile::isValidUri($tn_uri)) {
      $blazies->set($key . '.uri', $tn_uri);
      if (!$blazies->get($key . '.checked')) {
        if ($style && !is_file($tn_uri)) {
          $style->createDerivative($uri, $tn_uri);
        }
        $blazies->set($key . '.checked', TRUE);
      }
      return is_file($tn_uri);
    }
    return FALSE;
  }

  /**
   * Checks for blur settings, required Image style and dimensions setup.
   *
   * @see self::prepare()
   */
  private static function thumbnails(array &$settings): void {
    $blazies = $settings['blazies'];
    $style   = NULL;
    $width   = $height = 1;
    $uri     = $settings['uri'] ?? NULL;
    $uri     = $uri ?: $blazies->get('image.uri');
    $tn_uri  = $settings['thumbnail_uri'] ?? NULL;
    $tn_uri  = $tn_uri ?: $blazies->get('thumbnail.uri');
    $tn_url  = '';

    // Supports unique thumbnail different from main image, such as logo for
    // thumbnail and main image for company profile.
    if ($tn_uri) {
      $tn_url = BlazyFile::transformRelative($tn_uri);
    }
    else {
      // This one uses non-unique image, similar to the main stage image.
      $style = $blazies->get('thumbnail.style');
      if (!$blazies->is('external') && $style) {
        $tn_uri = $style->buildUri($uri);
        $tn_url = BlazyFile::transformRelative($uri, $style);

        [
          'width' => $width,
          'height' => $height,
        ] = BlazyImage::transformDimensions($style, $settings);
      }
    }

    // With CSS background, IMG may be empty, add thumbnail to the container.
    $blazies->set('thumbnail.url', $tn_url);
    if ($tn_url) {
      self::derivative($blazies, $uri, $tn_uri, $style, 'thumbnail');
    }

    // @todo use the thumbnail size, not original ones, see: #3210759?
    $blazies->set('placeholder.width', $width)
      ->set('placeholder.height', $height);

    // Accepts configurable placeholder, alter, and fallback.
    $default = self::generate($width, $height);
    $placeholder = $blazies->get('ui.placeholder') ?: $default;
    $blazies->set('placeholder.url', $placeholder);

    if ($blazies->get('resimage.id')) {
      BlazyResponsiveImage::fallback($settings, $placeholder);

      // @todo decide priority whether various thumbnails or one fallback style.
      // Thumbnail gives more selective styles per field than a single fallback.
      // If thumbnail, move it to the top. This is to preserve old behaviors.
      if ($restyle = $blazies->get('resimage.fallback.style')) {
        $style = $restyle;
      }
    }

    // Creates `data:image` for blur effect if so configured and applicable.
    self::dataImage($blazies, $uri, $tn_uri, $tn_url, $style);
  }

}
