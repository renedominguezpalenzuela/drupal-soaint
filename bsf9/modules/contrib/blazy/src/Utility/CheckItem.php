<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Blazy;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;

/**
 * Provides feature check methods at item level, or individually.
 *
 * @todo remove most $settings once migrated and after sub-modules and tests.
 */
class CheckItem {

  /**
   * Checks for essential settings: URI, delta, cache and initial delta.
   *
   * The initial delta related to option `Loading: slider`, the initial is not
   * lazyloaded, the rest are. Sometimes the initial delta is not always 0 as
   * normally seen at slider option name: `initial slide` or `start`.
   *
   * Image URI might be NULL given rich media like Facebook, etc., no problem.
   * That is why this is called twice. Once to check, another to re-check.
   */
  public static function essentials(array &$settings, $item = NULL, $delta = -1): void {
    $blazies = $settings['blazies'];
    $delta   = $settings['delta'] ?? $blazies->get('delta', $delta);
    $initial = $delta == $blazies->get('initial', -2);
    $uri     = BlazyFile::uri($item, $settings);

    // This means re-definition since URI can be fed from any sources uptream.
    // @todo remove uri for image.uri for better grouping.
    $blazies->set('uri', $uri)
      ->set('delta', $delta)
      ->set('is.initial', $initial)
      ->set('image.uri', $uri);

    // File cache tags.
    if ($item && ($file = ($item->entity ?? NULL))) {
      $tags = $file->getCacheTags();
      $blazies->set('cache.file.tags', $tags);
    }

    // @todo remove after sub-modules.
    $settings['delta'] = $delta;
    $settings['uri'] = $uri;
  }

  /**
   * Checks for multimedia settings, per item to address mixed media.
   *
   * @requires self::essentials()
   *
   * Bundles should not be coupled with embed_url to allow various bundles
   * and use media.source to be more precise instead.
   *
   * @todo remove $type, a legacy VEF period, which knew no bundles, or sources.
   */
  public static function multimedia(array &$settings): void {
    $blazies   = $settings['blazies'];
    $source    = $blazies->get('media.source');
    $type      = $settings['type'] ?? 'image';
    $type      = $settings['type'] = $blazies->get('media.type') ?: $type;
    $bundle    = $blazies->get('media.bundle', $settings['bundle'] ?? '');
    $embed_url = $settings['embed_url'] ?? '';
    $embed_url = $embed_url ?: $blazies->get('media.embed_url');
    $videos    = ['oembed:video', 'video_embed_field'];
    $medias    = array_merge(['audio_file', 'video_file'], $videos);
    $is_video  = $source && in_array($source, $videos) || $type == 'video';
    $is_media  = $source && in_array($source, $medias) || $is_video;
    $is_remote = $bundle == 'remote_video' || $type == 'video';
    $is_remote = $embed_url && ($is_video || $is_remote);
    $switch    = $settings['media_switch'] ?? NULL;
    $switch    = $switch ?: $blazies->get('switch');
    $is_iframe = $is_remote && empty($switch);
    $is_player = $is_remote && $switch == 'media';

    // BVEF compat without core OEmbed security feature.
    // @todo remove once BVEF adopted Blazy:2.10 BlazyVideoFormatter.
    if ($is_player && strpos($embed_url, 'media/oembed') === FALSE) {
      $embed_url = Blazy::autoplay($embed_url);
    }

    // Addresses mixed media unique per item, aside from convenience.
    // Also compat with BVEF till they are updated to adopt 2.10 changes.
    $blazies->set('is.iframe', $is_iframe)
      ->set('is.multimedia', $is_media)
      ->set('is.player', $is_player)
      ->set('is.remote_video', $is_remote)
      ->set('is.video_file', $source == 'video_file')
      ->set('media.embed_url', $embed_url)
      ->set('media.type', $type)
      ->set('switch', $switch);
  }

  /**
   * Checks if an extension should not use image style: apng svg gif, etc.
   *
   * @requires self::essentials(), self::multimedia()
   */
  public static function unstyled(array &$settings) {
    $blazies = $settings['blazies'];
    $uri = $blazies->get('image.uri');
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $unstyled = BlazyImage::isUnstyled($uri, $settings, $ext);

    // Disable image style if so configured.
    // Extensions without image styles: animated GIF, APNG, SVG, etc.
    if ($unstyled) {
      $images = ['box', 'box_media', 'image', 'thumbnail', 'responsive_image'];
      foreach ($images as $image) {
        $settings[$image . '_style'] = '';
        $blazies->set('image.style', NULL);
      }
    }

    // Re-define, if the provided API by-passed, or different/ altered per item.
    $blazies->set('is.external', UrlHelper::isExternal($uri))
      ->set('is.unstyled', $unstyled)
      ->set('image.extension', $ext);

    // ResponsiveImage is the most temperamental module. Unlike plain old Image,
    // it explodes when the image is missing as much as when fed wrong URI, etc.
    // Do not let SVG alike mess up with ResponsiveImage, else fatal.
    if (!$unstyled) {
      if ($style = BlazyResponsiveImage::toStyle($settings, $unstyled)) {
        $blazies->set('resimage.style', $style);

        // Might be set via BlazyFilter, but not enough data passed.
        $multiple = $blazies->is('multistyle');
        if (!$blazies->get('resimage.id') || $multiple) {
          BlazyResponsiveImage::define($blazies, $style);
        }

        // We'll bail out internally if already set once at container level.
        BlazyResponsiveImage::dimensions($settings, $style, FALSE);
      }
    }
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * @requires self::multimedia()
   *
   * Some duplicate rules are to address non-blazy formatters like embedded
   * Image formatter within Blazy ecosystem, but not using Blazy formatter, etc.
   * The lazy insanity:
   * - Respects `No JavaScript: lazy` aka decoupled lazy loader.
   * - Respects `Loading priority` to avoid anti-pattern.
   * - Respects `Loading: slider`, the initial is not lazyloaded, the rest are.
   * - Respects sub-module lazy attributes and methods:
   *   - Splide: nearby and sequential.
   *   - Slick: anticipated, ondemand and progressive.
   *   Unless they are incapable of dealing with: iframe, BG, Picture, BG, etc.
   *
   * @todo needs a recap to move some container-level here if they must live at
   * individual level, such as non-blazy Image formatter within Blazy ecosystem.
   */
  public static function insanity(array &$settings): void {
    $blazies    = $settings['blazies'];
    $ratio      = $settings['ratio'] ?? '';
    $unlazy     = $blazies->is('slider') && $blazies->is('initial');
    $unlazy     = $unlazy ? TRUE : $blazies->is('unlazy');
    $use_loader = $settings['use_loading'] ?? $blazies->get('use.loader');
    $use_loader = $unlazy ? FALSE : $use_loader;
    $is_unblur  = $blazies->is('sandboxed')
      || $blazies->is('unstyled') || $blazies->is('iframe');
    $is_blazy   = $blazies->get('lazy.id') == 'blazy' && $blazies->is('blazy');
    $is_blur    = !$is_unblur && ($blazies->is('blur') && $is_blazy);

    // Supports core Image formatter embedded within Blazy ecosystem.
    $is_fluid = $blazies->is('fluid') ?: $ratio == 'fluid';

    // @todo better logic to support loader as required, must decouple loader.
    // @todo $lazy = $settings['loading'] == 'lazy';
    // @todo $lazy = $blazies->is('blazy') && ($blazies->get('libs.compat') || $lazy);
    // Redefines some since this can be fed by anyone, including custom works.
    $blazies->set('is.fluid', $is_fluid)
      ->set('is.blur', $is_blur)
      ->set('is.unlazy', $unlazy)
      ->set('use.loader', $use_loader)
      ->set('was.prepare', TRUE);

    // Also disable blur effect attributes.
    if (!$is_blur && $blazies->get('fx') == 'blur') {
      $blazies->set('fx', NULL);
    }

    // Overrides sub-modules which know not iframe, Picture, Video, BG, Blur.
    if ($is_blazy || $is_blur) {
      $blazies->set('lazy.attribute', 'src')
        ->set('lazy.class', 'b-lazy')
        ->set('lazy.id', 'blazy')
        ->set('is.blazy', TRUE);
    }
  }

}
