<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyDependenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for blazy/slick image, and file ER formatters.
 *
 * Defines one base class to extend for both image and file ER formatters as
 * otherwise different base classes: ImageFormatterBase or FileFormatterBase.
 *
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatter.
 * @see Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFileFormatter.
 * @see Drupal\slick\Plugin\Field\FieldFormatter\SlickImageFormatter.
 * @see Drupal\slick\Plugin\Field\FieldFormatter\SlickFileFormatter.
 *
 * @todo remove no longer in use: ImageFactory at blazy:3.x.
 */
abstract class BlazyFileFormatterBase extends FileFormatterBase {

  use BlazyFormatterTrait {
    getScopedFormElements as traitGetScopedFormElements;
  }
  use BlazyDependenciesTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'image');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::imageSettings() + BlazyDefault::gridSettings();
  }

  /**
   * Build individual item if so configured such as for file ER goodness.
   */
  public function buildElement(array &$element, $entity) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);
    $this->admin()->buildSettingsForm($element, $definition);

    return $element;
  }

  /**
   * Returns the Blazy elements, also for sub-modules to re-use.
   */
  protected function getElements(array &$build, $files, $caption_id = 'captions'): array {
    $elements = [];
    foreach ($files as $delta => $file) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      $item  = $file->_referringItem;
      $sets  = $build['settings'];
      $blazy = $sets['blazies']->reset($sets);
      $uri   = $sets['uri'] = $file->getFileUri();

      // @todo update tests and move it out of here.
      $blazy->set('delta', $delta)
        ->set('media.type', 'image')
        ->set('image.uri', $uri);

      $element = ['item' => $item, 'settings' => $sets];

      // Build individual element.
      $this->buildElement($element, $file);

      // Build captions if so configured.
      if ($caption_id) {
        $this->buildCaptions($element, $caption_id);
      }

      // Image with grid, responsive image, lazyLoad, and lightbox supports.
      $elements[] = $element;
    }
    return $elements;
  }

  /**
   * Builds the captions.
   */
  protected function buildCaptions(array &$element, $caption_id): void {
    $settings = $element['settings'];
    if (!empty($settings['caption']) && $item = ($element['item'] ?? NULL)) {
      foreach ($settings['caption'] as $caption) {
        if ($content = ($item->{$caption} ?? NULL)) {
          $element[$caption_id][$caption] = [
            '#markup' => Xss::filterAdmin($content),
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $multiple = $this->isMultiple();
    $captions = ['title' => $this->t('Title'), 'alt' => $this->t('Alt')];

    return [
      'background'        => TRUE,
      'box_captions'      => TRUE,
      'captions'          => $captions,
      'grid_form'         => $multiple,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'style'             => $multiple,
      'thumbnail_style'   => TRUE,
    ];
  }

  /**
   * Returns TRUE if a multi-value field.
   */
  protected function isMultiple(): bool {
    return $this->fieldDefinition
      ->getFieldStorageDefinition()
      ->isMultiple();
  }

  /**
   * Overrides parent::needsEntityLoad().
   *
   * One step back to have both image and file ER plugins extend this, because
   * EntityReferenceItem::isDisplayed() doesn't exist, except for ImageItem
   * which is always TRUE anyway for type image and file ER.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   *
   * A clone of Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase so
   * to have one base class to extend for both image and file ER formatters.
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    // Add the default image if the type is image.
    if ($items->isEmpty() && $this->fieldDefinition->getType() === 'image') {
      $default_image = $this->getFieldSetting('default_image');
      // If we are dealing with a configurable field, look in both
      // instance-level and field-level settings.
      if (empty($default_image['uuid']) && $this->fieldDefinition instanceof FieldConfigInterface) {
        $default_image = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('default_image');
      }
      if (!empty($default_image['uuid']) && $file = $this->formatter->loadByUuid($default_image['uuid'], 'file')) {
        // Clone the FieldItemList into a runtime-only object for the formatter,
        // so that the fallback image can be rendered without affecting the
        // field values in the entity being rendered.
        $items = clone $items;
        $items->setValue([
          'target_id' => $file->id(),
          'alt' => $default_image['alt'],
          'title' => $default_image['title'],
          'width' => $default_image['width'],
          'height' => $default_image['height'],
          'entity' => $file,
          '_loaded' => TRUE,
          '_is_default' => TRUE,
        ]);
        $file->_referringItem = $items[0];
      }
    }

    return parent::getEntitiesToView($items, $langcode);
  }

}
