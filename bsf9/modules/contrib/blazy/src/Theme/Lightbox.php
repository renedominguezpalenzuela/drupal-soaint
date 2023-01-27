<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;

/**
 * Provides lightbox utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Lightbox {

  /**
   * Provides lightbox libraries.
   */
  public static function attach(array &$load, array &$attach = []): void {
    $blazies = $attach['blazies'];

    if ($name = $blazies->get('lightbox.name')) {
      $load['library'][] = 'blazy/lightbox';

      // Built-in lightboxes.
      if ($name == 'colorbox') {
        self::attachColorbox($load);
      }
      foreach (['colorbox', 'mfp', 'photobox'] as $key) {
        if ($name == $key) {
          $blazies->set('libs.' . $key, TRUE);
        }
      }
    }
  }

  /**
   * Gets media switch elements: all lightboxes, not content, nor iframe.
   *
   * @param array $element
   *   The element being modified.
   */
  public static function build(array &$element = []): void {
    $manager    = Blazy::service('blazy.manager');
    $item       = $element['#item'];
    $settings   = &$element['#settings'];
    $blazies    = $settings['blazies'];
    $uri        = $blazies->get('image.uri', $settings['uri'] ?? '');
    $switch     = $blazies->get('lightbox.name');
    $switch_css = str_replace('_', '-', $switch);
    $valid      = BlazyFile::isValidUri($uri);
    $_box_style = $settings['box_style'] ?? NULL;
    $box_style  = $blazies->get('box.style');
    $box_url    = $url = BlazyFile::transformRelative($uri);
    $colorbox   = $blazies->get('colorbox');
    $gallery_id = $blazies->get('lightbox.gallery_id');
    $box_id     = !$blazies->is('gallery') ? NULL : $gallery_id;
    $box_width  = $item->width ?? $blazies->get('image.original.width');
    $box_height = $item->height ?? $blazies->get('image.original.height');

    // Provide relevant URL if it is a lightbox.
    $url_attributes = &$element['#url_attributes'];
    $url_attributes['class'][] = 'blazy__' . $switch_css . ' litebox';
    $url_attributes['data-' . $switch_css . '-trigger'] = TRUE;

    $dimensions = [
      'width' => $box_width,
      'height' => $box_height,
      'uri' => $uri,
    ];

    // Might not be present from BlazyFilter.
    $json = ['id' => $switch_css];
    foreach (['bundle', 'type'] as $key) {
      $default = $settings[$key] ?? '';
      if ($value = $blazies->get('media.' . $key, $default)) {
        $json[$key] = $value;
      }
    }

    // Supports local and remote videos, also legacy VEF which has no bundles.
    // See https://drupal.org/node/3210636#comment-14097266.
    $is_multimedia = $blazies->is('multimedia');
    $ok = $valid && $_box_style && !$blazies->is('unstyled');
    if (!$is_multimedia && $ok) {
      // Change xdebug.show_exception_trace = 1 to 0 to catch exceptions.
      // The _responsive_image_build_source_attributes is WSOD if missing.
      if ($blazies->is('resimage')) {
        try {
          $resimage = $manager->entityLoad($_box_style, 'responsive_image_style');
          if (empty($element['#lightbox_html']) && $resimage) {
            $is_resimage = TRUE;
            $json['type'] = 'rich';
            $element['#lightbox_html'] = [
              '#theme' => 'responsive_image',
              '#responsive_image_style_id' => $resimage->id(),
              '#uri' => $uri,
            ];
          }
        }
        catch (\Exception $e) {
          // Silently failed like regular images when missing rather than WSOD.
        }
      }

      // Use non-responsive images if not-so-configured.
      if (!isset($is_resimage) && $box_style) {
        $dimensions = array_merge($dimensions, BlazyImage::transformDimensions($box_style, $dimensions));
        $box_url = $url = BlazyFile::transformRelative($uri, $box_style);
      }
    }

    // Allows custom work to override this without image style, such as
    // a combo of image, video, Instagram, Facebook, etc.
    if (empty($settings['_box_width'])) {
      $box_width = $dimensions['width'];
      $box_height = $dimensions['height'];
    }

    $json['width'] = $box_width;
    $json['height'] = $box_height;
    $json['boxType'] = 'image';

    // This allows PhotoSwipe with videos still swipable.
    $box_media_url = NULL;
    if ($valid && $box_media_style = $blazies->get('box_media.style')) {
      $dimensions = array_merge($dimensions, BlazyImage::transformDimensions($box_media_style, $dimensions));
      $box_media_url = BlazyFile::transformRelative($uri, $box_media_style);
    }

    if ($is_multimedia) {
      $json['width']  = 640;
      $json['height'] = 360;

      if ($embed = $blazies->get('media.embed_url')) {
        // Force autoplay for media URL on lightboxes, saving another click.
        // BC for non-oembed such as Video Embed Field without Media migration.
        $url = Blazy::autoplay($embed);

        $url_attributes['data-oembed-url'] = $url;
        $json['boxType'] = 'iframe';
      }

      // Remote or local videos.
      if ($box_media_url) {
        // This allows PhotoSwipe with remote videos still swipable.
        $box_url = $box_media_url;
        $json['width'] = $box_width = $dimensions['width'];
        $json['height'] = $box_height = $dimensions['height'];
      }

      if ($blazies->get('photobox')) {
        $url_attributes['rel'] = 'video';
      }

      if ($box_url) {
        $url_attributes['data-box-url'] = $box_url;
      }
    }

    // @todo remove after sub-modules.
    $settings['box_url'] = $box_url;
    $blazies->set('lightbox.url', $box_url)
      ->set('lightbox.width', (int) $box_width)
      ->set('lightbox.height', (int) $box_height)
      ->set('lightbox.media_preview_url', $box_media_url);

    if ($colorbox && $box_id) {
      // @todo make Blazy Grid without Blazy Views fields support multiple
      // fields and entities as a gallery group, likely via a class at Views UI.
      // Must use consistent key for multiple entities, hence cannot use id.
      // We do not have option for this like colorbox, as it is only limited
      // to the known Blazy formatters, or Blazy Views style plugins for now.
      // The hustle is Colorbox wants rel on individual item to group, unlike
      // other lightbox library which provides a way to just use a container.
      $json['rel'] = $box_id;
    }

    $has_dim = !empty($json['height']) && !empty($json['width']);
    if ($has_dim) {
      $json['height'] = (int) $json['height'];
      $json['width'] = (int) $json['width'];
    }

    // @todo make is flexible for regular non-media HTML.
    if (!empty($element['#lightbox_html'])) {
      $html = [
        '#theme' => 'container',
        '#children' => $element['#lightbox_html'],
        '#attributes' => [
          'class' => ['media', 'media--ratio'],
        ],
      ];

      if ($has_dim) {
        $pad = round((($json['height'] / $json['width']) * 100), 2);
        $html['#attributes']['style'] = 'width:' . $json['width'] . 'px; padding-bottom: ' . $pad . '%;';
      }

      // Responsive image is unwrapped. Local videos wrapped.
      $content = isset($is_resimage) ? $element['#lightbox_html'] : $html;
      $content = $manager->getRenderer()->renderPlain($content);
      $json['html'] = trim($content);
      if (isset($is_resimage)) {
        $json['boxType'] = strpos($content, '<picture') !== FALSE ? 'picture' : 'responsive-image';
      }
      else {
        if (strpos($content, '<video') !== FALSE) {
          $json['boxType'] = 'video';
        }
      }

      unset($element['#lightbox_html']);
    }

    $url_attributes['data-media'] = Json::encode($json);

    if (!empty($settings['box_caption'])) {
      $element['#captions']['lightbox'] = self::buildCaptions($item, $settings);
    }

    $icon = '<span class="media__icon media__icon--litebox"></span>';
    $element['#url'] = $url;
    $element['#icon']['lightbox']['#markup'] = $icon;
  }

  /**
   * Attaches Colorbox if so configured.
   */
  private static function attachColorbox(array &$load): void {
    if ($service = Blazy::service('colorbox.attachment')) {
      $dummy = [];
      $service->attach($dummy);

      if (isset($dummy['#attached'])) {
        $load = NestedArray::mergeDeep($load, $dummy['#attached']);
      }

      unset($dummy);
    }
  }

  /**
   * Builds lightbox captions.
   *
   * @param object|mixed $item
   *   The \Drupal\image\Plugin\Field\FieldType\ImageItem item.
   * @param array $settings
   *   The settings to work with.
   *
   * @return array
   *   The renderable array of caption, or empty array.
   */
  private static function buildCaptions($item, array $settings = []): array {
    $blazies = $settings['blazies'];
    $title   = $item->title ?? '';
    $alt     = $item->alt ?? '';
    $delta   = $blazies->get('delta');
    $object  = NULL;

    // @todo re-check this if any issues, might be a fake stdClass image item.
    if ($item) {
      $object = method_exists($item, 'getEntity')
        ? $item->getEntity() : $item->entity;
    }

    $entity  = $blazies->get('entity.instance') ?: $object;
    $caption = '';

    switch ($settings['box_caption']) {
      case 'auto':
        $caption = $alt ?: $title;
        break;

      case 'alt':
        $caption = $alt;
        break;

      case 'title':
        $caption = $title;
        break;

      case 'alt_title':
      case 'title_alt':
        $alt     = $alt ? '<p>' . $alt . '</p>' : '';
        $title   = $title ? '<h2>' . $title . '</h2>' : '';
        $caption = $settings['box_caption'] == 'alt_title' ? $alt . $title : $title . $alt;
        break;

      case 'entity_title':
        $caption = $entity && method_exists($entity, 'label')
          ? $entity->label() : '';
        break;

      case 'custom':
        $caption = '';

        if (!empty($settings['box_caption_custom']) && $object) {
          $options = ['clear' => TRUE];
          $caption = \Drupal::token()->replace($settings['box_caption_custom'], [
            $object->getEntityTypeId() => $object,
          ], $options);

          // Checks for multi-value text fields, and maps its delta to image.
          if (!empty($caption) && strpos($caption, ", <p>") !== FALSE) {
            $caption = str_replace(", <p>", '| <p>', $caption);
            $captions = explode("|", $caption);
            $caption = $captions[$delta] ?? '';
          }
        }
        break;

      default:
        $caption = $settings['box_caption'] == 'inline' ? '' : $settings['box_caption'];
    }

    return empty($caption)
      ? []
      : ['#markup' => Xss::filter($caption, BlazyDefault::TAGS)];
  }

}
