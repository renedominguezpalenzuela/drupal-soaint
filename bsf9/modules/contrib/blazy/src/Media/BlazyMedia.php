<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\NestedArray;
use Drupal\media\MediaInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides extra utilities to work with core Media.
 *
 * This class makes it possible to have a mixed display of all media entities,
 * useful for Blazy Grid, Slick Carousel, GridStack contents as mixed media.
 * This approach is alternative to regular preprocess overrides, still saner
 * than iterating over unknown like template_preprocess_media_entity_BLAH, etc.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Media integration is being reworked.
 *
 * @todo rework this for core Media, and refine for theme_blazy(). Two big TODOs
 * for the next releases is to replace ImageItem references into just $settings,
 * and convert this into non-static to move most BlazyOEmbed stuffs here.
 * Not urgent, the important is to make it just work with minimal regressions.
 * @todo recap similiraties and make them plugins.
 */
class BlazyMedia {

  /**
   * Builds the media field which is not understood by theme_blazy().
   *
   * @param object $media
   *   The media being rendered.
   * @param array $settings
   *   The contextual settings array.
   *
   * @return array
   *   The renderable array of the media field, or empty if not applicable.
   */
  public static function build($media, array $settings = []): array {
    $blazies = $settings['blazies'];
    // Prevents fatal error with disconnected internet when having ME Facebook,
    // ME SlideShare, resorted to static thumbnails to avoid broken displays.
    if ($input = $blazies->get('media.input_url')) {
      try {
        \Drupal::httpClient()->get($input, ['timeout' => 3]);
      }
      catch (\Exception $e) {
        return [];
      }
    }

    $settings['type'] = $type = 'rich';
    $blazies->set('media.type', $type);

    $is_local = $blazies->get('media.source', $settings['media_source'] ?? '') == 'video_file';
    $view_mode = $blazies->get('media.view_mode', $settings['view_mode'] ?? 'default');
    $options = $is_local ? ['type' => 'file_video'] : $view_mode;
    $source_field = $blazies->get('media.source_field', $settings['source_field'] ?? '');

    $build = $media->get($source_field)->view($options);
    $build['#settings'] = $settings;

    return isset($build[0]) ? self::unfield($build) : $build;
  }

  /**
   * Returns a field item/ content to be wrapped by theme_blazy().
   *
   * @param array $field
   *   The source renderable array $field.
   *
   * @return array
   *   The renderable array of the media item to be wrapped by theme_blazy().
   */
  public static function unfield(array $field = []): array {
    $item     = $field[0];
    $settings = &$field['#settings'];
    $blazies  = $settings['blazies'];
    $iframe   = isset($item['#tag']) && $item['#tag'] == 'iframe';

    if (isset($item['#attributes'])) {
      $attributes = &$item['#attributes'];
    }
    else {
      $attributes = [];
    }

    // Update iframe/video dimensions based on configurable image style, if any.
    foreach (['width', 'height'] as $key) {
      if (!empty($settings[$key])) {
        $attributes[$key] = $settings[$key];
      }
    }

    // Converts iframes into lazyloaded ones.
    // Iframes: Googledocs, SlideShare. Hardcoded: Soundcloud, Spotify.
    if ($iframe && $src = ($attributes['src'] ?? FALSE)) {
      $blazies->set('media.embed_url', $src);
      $attributes = NestedArray::mergeDeep($attributes, BlazyAttribute::iframe($settings));
    }
    // Media with local files: video.
    elseif (isset($item['#files']) && $file = ($item['#files'][0]['file'] ?? NULL)) {
      // @todo multiple sources, not crucial for now.
      $blazies->set('media.uri', $file->getFileUri());
      self::videoItem($item, $settings);
    }

    // Clone relevant keys since field wrapper is no longer in use.
    foreach (['attached', 'cache', 'third_party_settings'] as $key) {
      if (!empty($field["#$key"])) {
        $item["#$key"] = isset($item["#$key"]) ? NestedArray::mergeDeep($field["#$key"], $item["#$key"]) : $field["#$key"];
      }
    }
    // Keep original formatter configurations intact here for custom works.
    $item['#settings'] = new BlazySettings(array_filter($settings));
    return $item;
  }

  /**
   * Extracts neededinfo from a media.
   */
  public static function extract(MediaInterface $media, $view_mode = NULL): array {
    return [
      'bundle'       => $media->bundle(),
      'id'           => $media->id(),
      'label'        => $media->label(),
      'source'       => $media->getSource()->getPluginId(),
      'source_field' => $media->getSource()->getConfiguration()['source_field'],
      'url'          => $media->isNew() ? '' : $media->toUrl()->toString(),
      'view_mode'    => $view_mode ?: 'default',
    ];
  }

  /**
   * Prepares media item data to provide image item.
   */
  public static function prepare(array &$data, MediaInterface &$media) {
    $settings  = $data['settings'];
    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? NULL;
    $langcode  = $blazies->get('language.current');

    // Provides translated $media, if any.
    $media = Blazy::translated($media, $langcode);

    // Provides settings.
    $info = self::extract($media, $view_mode);

    $blazies->set('media', $info, TRUE);

    // @todo remove $settings for $blazies after migration and sub-modules.
    foreach ($info as $key => $value) {
      $key = in_array($key, ['id', 'url', 'source']) ? 'media_' . $key : $key;
      $settings[$key] = $value;
    }
  }

  /**
   * Modifies item attributes for local video item.
   */
  private static function videoItem(array &$item, array $settings): void {
    // Do this as $item['#settings'] is not available as file_video variables.
    // @todo re-check, most like just a single file here.
    foreach ($item['#files'] as &$files) {
      $files['blazy'] = new BlazySettings($settings);
    }

    $item['#attributes']->setAttribute('data-b-lazy', TRUE);
    if ($blazies = ($settings['blazies'] ?? NULL)) {
      if ($blazies->is('undata')) {
        $item['#attributes']->setAttribute('data-b-undata', TRUE);
      }
    }
  }

  /**
   * Extracts image from non-media entities for the main background/ stage.
   *
   * @todo remove after sub-modules.
   */
  public static function imageItem(array &$data, $entity): void {
    $settings = &$data['settings'];
    if ($stage = ($settings['image'] ?? FALSE)) {
      BlazyImage::fromField($data, $entity, $stage);
    }
  }

}
