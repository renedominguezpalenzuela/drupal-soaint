<?php

namespace Drupal\blazy\Field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\BlazyDefault;

/**
 * Base class for Media entity reference formatters with field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityReferenceBase
 */
abstract class BlazyEntityMediaBase extends BlazyEntityVanillaBase {

  use BlazyDependenciesTrait;

  /**
   * {@inheritdoc}
   */
  public function buildElements(array &$build, $entities, $langcode) {
    parent::buildElements($build, $entities, $langcode);

    $settings = $build['settings'];
    $blazies  = $settings['blazies'];
    $item_id  = $blazies->get('item.id');

    // Some formatter has a toggle Vanilla.
    if (empty($settings['vanilla'])) {
      // Supports Blazy formatter multi-breakpoint images if available.
      if ($item = ($build['items'][0] ?? NULL)) {
        $fallback = $item[$item_id]['#build'] ?? [];
        $data = $item['#build'] ?? $fallback;
        if ($data) {
          $blazies->set('first.data', $data);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta): void {
    parent::prepareElement($build, $entity, $langcode, $delta);

    $settings  = $build['settings'];
    $blazies   = $settings['blazies'];
    $item_id   = $blazies->get('item.id');
    $view_mode = $settings['view_mode'] ?? 'full';
    $is_nav    = $blazies->is('nav') || !empty($settings['nav']);
    $switch    = $settings['media_switch'] ?? NULL;

    // Bail out if vanilla (rendered entity) is required.
    if (!empty($settings['vanilla'])) {
      return;
    }

    // Otherwise hard work which is meant to reduce custom code at theme level.
    $element = ['item' => NULL, 'settings' => $settings];

    // Build media item including custom highres video thumbnail.
    $this->blazyOembed->build($element, $entity);

    // Captions if so configured, including Blazy formatters.
    $this->getCaption($element, $entity, $langcode);

    // If `Image rendered` is picked, render image as is. Might not be Blazy's
    // formatter, yet has awesomeness that Blazy doesn't, but still wants to be
    // embedded in Blazy ecosytem mostly for Grid, Slider, Mason, GridStack etc.
    if (!empty($settings['image']) && $switch == 'rendered') {
      $element['content'][] = BlazyField::view($entity, $settings['image'], $view_mode);
    }

    // Optional image with responsive image, lazyLoad, and lightbox supports.
    // Including potential rich Media contents: local video, Facebook, etc.
    $blazy = $this->formatter->getBlazy($element, $delta);

    // If the caller is Blazy, provides simple index elements.
    if ($blazies->get('namespace') == 'blazy') {
      $build['items'][$delta] = $blazy;
    }
    else {
      // Otherwise Slick, GridStack, Mason, etc. may need more elements.
      $element[$item_id] = $blazy;

      // Provides extra elements.
      $this->buildElementExtra($element, $entity, $langcode);

      // Build the main item.
      $build['items'][$delta] = $element;

      // Build the thumbnail item.
      if ($is_nav) {
        $this->buildElementThumbnail($build, $element, $entity, $delta);
      }
    }
  }

  /**
   * Build extra elements.
   */
  public function buildElementExtra(array &$element, $entity, $langcode) {
    // Do nothing, let extenders do their jobs.
  }

  /**
   * Build thumbnail navigation such as for Slick asnavfor.
   */
  public function buildElementThumbnail(array &$build, $element, $entity, $delta) {
    // Do nothing, let extenders do their jobs.
  }

  /**
   * Builds captions with possible multi-value fields.
   */
  public function getCaption(array &$element, $entity, $langcode) {
    $settings  = $element['settings'];
    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? 'full';

    // The caption fields common to all entity formatters, if so configured.
    if (empty($settings['caption'])) {
      return;
    }

    $caption_items = $weights = [];
    foreach ($settings['caption'] as $name => $field_caption) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      if ($item = ($element['item'] ?? NULL)) {
        // Provides basic captions based on image attributes (Alt, Title).
        foreach (['title', 'alt'] as $key => $attribute) {
          $value = $item->{$attribute} ?? '';
          if ($name == $attribute && $caption = trim($value)) {
            $markup = Xss::filter($caption, BlazyDefault::TAGS);
            $caption_items[$name] = ['#markup' => $markup];
            $weights[] = $key;
          }
        }
      }

      // Provides fieldable captions.
      if ($caption = BlazyField::view($entity, $field_caption, $view_mode)) {
        if (isset($caption['#weight'])) {
          $weights[] = $caption['#weight'];
        }

        $caption_items[$name] = $caption;
      }
    }

    if ($caption_items) {
      if ($weights) {
        array_multisort($weights, SORT_ASC, $caption_items);
      }

      // Differenciate Blazy from Slick, GridStack, etc. to avoid collisions.
      if ($blazies->get('namespace') == 'blazy') {
        $element['captions'] = $caption_items;
      }
      else {
        $element['caption']['data'] = $caption_items;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if (isset($element['media_switch'])) {
      $element['media_switch']['#options']['rendered'] = $this->t('Image rendered by its formatter');
      $element['media_switch']['#description'] .= ' ' . $this->t('<b>Image rendered</b> requires <b>Image</b> option filled out and is useful if the formmater offers awesomeness that Blazy does not have but still wants Blazy for a Grid, etc. Be sure the enabled fields here are not hidden/ disabled at its view mode.');
    }

    if (isset($element['caption'])) {
      $element['caption']['#description'] = $this->t('Check fields to be treated as captions, even if not caption texts.');
    }

    if (isset($element['image']['#description'])) {
      $element['image']['#description'] .= ' ' . $this->t('For (remote|local) video, this allows separate high-res or poster image. Be sure this exact same field is also used for bundle <b>Image</b> to have a mix of videos and images if this entity is Media. Leaving it empty will fallback to the video provider thumbnails, or no poster for local video. The formatter/renderer is managed by <strong>@plugin_id</strong> formatter. Meaning original formatter ignored.', ['@plugin_id' => $this->getPluginId()]);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $bundles  = $this->getAvailableBundles();
    $captions = $this->getFieldOptions();
    $images   = [];

    if ($bundles) {
      // @todo figure out to not hard-code stock bundle image.
      if (in_array('image', array_keys($bundles))) {
        $captions['title'] = $this->t('Image Title');
        $captions['alt'] = $this->t('Image Alt');
      }

      // Only provides poster if media contains rich media.
      $media = ['audio', 'remote_video', 'video', 'instagram', 'soundcloud'];
      if (count(array_intersect(array_keys($bundles), $media)) > 0) {
        $images['images'] = $this->getFieldOptions(['image']);
      }
    }

    // @todo better way than hard-coding field name.
    unset($captions['field_image'], $captions['field_media_image']);

    return [
      'background'        => TRUE,
      'box_captions'      => TRUE,
      'captions'          => $captions,
      'fieldable_form'    => TRUE,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
      'no_layouts'        => FALSE,
      'no_image_style'    => FALSE,
      'responsive_image'  => TRUE,
    ] + $images + parent::getPluginScopes();
  }

}
