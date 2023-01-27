<?php

namespace Drupal\blazy\Field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Element;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\BlazyMedia;

/**
 * Provides common field API operation methods.
 */
class BlazyField {

  /**
   * Returns file view or media due to being empty returned by view builder.
   *
   * @todo make it usable for other file-related entities.
   */
  public static function getOrViewMedia($file, array $settings, $rendered = TRUE) {
    // Might be accessed by tests, or anywhere outside the workflow.
    Blazy::verify($settings);

    $manager = Blazy::service('blazy.manager');
    $blazies = $settings['blazies'];
    [$type] = explode('/', $file->getMimeType(), 2);

    if ($type == 'video') {
      // As long as you are not being too creative by renaming or changing
      // fields provided by core, this should be your good friend.
      $blazies->set('media.source', 'video_file');
      $blazies->set('media.source_field', 'field_media_video_file');
    }

    $source_field = $blazies->get('media.source_field');
    if ($blazies->get('media.source') && $source_field) {
      $media = $manager->loadByProperties([
        $source_field => ['fid' => $file->id()],
      ], 'media', TRUE);

      if ($media = reset($media)) {
        return $rendered ? BlazyMedia::build($media, $settings) : $media;
      }
    }
    return [];
  }

  /**
   * Returns the string value of the fields: link, or text.
   */
  public static function getString($entity, $field_name, $langcode, $clean = TRUE): string {
    if ($entity->hasField($field_name)) {
      $values = self::getValue($entity, $field_name, $langcode);

      // Can be text, or link field.
      $string = $values[0]['uri'] ?? ($values[0]['value'] ?? '');

      if ($string && is_string($string)) {
        $string = $clean
          ? strip_tags($string, '<a><strong><em><span><small>')
          : Xss::filter($string, BlazyDefault::TAGS);
        return trim($string);
      }
    }
    return '';
  }

  /**
   * Returns the text or link value of the fields: link, or text.
   */
  public static function getTextOrLink($entity, $field_name, $view_mode, $langcode, $multiple = TRUE): array {
    if ($entity->hasField($field_name)) {
      if ($text = self::getValue($entity, $field_name, $langcode)) {
        if (!empty($text[0]['value']) && !isset($text[0]['uri'])) {
          // Prevents HTML-filter-enabled text from having bad markups (h2 > p),
          // except for a few reasonable tags acceptable within H2 tag.
          $text = self::getString($entity, $field_name, $langcode, FALSE);
        }
        elseif (isset($text[0]['uri'])) {
          $text = self::view($entity, $field_name, $view_mode, $multiple);
        }

        // Prevents HTML-filter-enabled text from having bad markups
        // (h2 > p), save for few reasonable tags acceptable within H2 tag.
        return is_string($text)
          ? ['#markup' => strip_tags($text, '<a><strong><em><span><small>')]
          : $text;
      }
    }
    return [];
  }

  /**
   * Returns the value of the fields: link, or text.
   */
  public static function getValue($entity, $field_name, $langcode) {
    if ($entity->hasField($field_name)) {
      $entity = Blazy::translated($entity, $langcode);

      return $entity->get($field_name)->getValue();
    }
    return NULL;
  }

  /**
   * Provides field-related settings.
   */
  public static function settings(array &$settings, array $data, $field): void {
    // @todo remove for blazies after admin updated and sub-modules.
    $settings['blazies'] = $blazies = Blazy::settings();
    $info = [
      'field_label' => $field->getLabel(),
      'field_name'  => $field->getName(),
      'field_type'  => $field->getType(),
      'entity_type' => $field->getTargetEntityTypeId(),
    ];

    foreach ($data as $key => $value) {
      $blazies->set('field.' . $key, $value);
    }

    foreach ($info as $key => $value) {
      $k = str_replace('field_', '', $key);
      $blazies->set('field.' . $k, $value);

      // @todo remove at 3.x after sub-modules.
      $settings[$key] = $value;
    }
  }

  /**
   * Returns the formatted renderable array of the field.
   */
  public static function view($entity, $field_name, $view_mode, $multiple = TRUE) {
    if ($entity->hasField($field_name)) {
      $view = $entity->get($field_name)->view($view_mode);

      if (empty($view[0])) {
        return [];
      }

      // Prevents quickedit to operate here as otherwise JS error.
      // @see 2314185, 2284917, 2160321.
      // @see quickedit_preprocess_field().
      // @todo Remove when it respects plugin annotation.
      $view['#view_mode'] = '_custom';
      $weight = $view['#weight'] ?? 0;

      // Intentionally clean markups as this is not meant for vanilla.
      if ($multiple) {
        $items = [];
        foreach (Element::children($view) as $key) {
          $items[$key] = $view[$key];
        }

        $items['#weight'] = $weight;
        return $items;
      }
      return $view[0];
    }

    return [];
  }

}
