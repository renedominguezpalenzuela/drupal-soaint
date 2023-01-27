<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\media\Entity\MediaType;
use Drupal\media\Plugin\media\Source\OEmbedInterface;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Field\BlazyDependenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for blazy oembed formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_oembed",
 *   label = @Translation("Blazy"),
 *   field_types = {
 *     "link",
 *     "string",
 *     "string_long",
 *   }
 * )
 *
 * @see \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase
 * @see \Drupal\media\Plugin\Field\FieldFormatter\OEmbedFormatter
 */
class BlazyOEmbedFormatter extends FormatterBase {

  use BlazyDependenciesTrait;
  use BlazyFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'entity');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::baseImageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return $this->commonViewElements($items, $langcode);
  }

  /**
   * Build the blazy elements.
   */
  public function buildElements(array &$build, $items) {
    $settings   = &$build['settings'];
    $blazies    = $settings['blazies'];
    $field_name = $this->fieldDefinition->getName();
    $entity     = $items->getParent()->getEntity();

    foreach ($items as $delta => $item) {
      $main_property = $item->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getMainPropertyName();

      $value = $item->{$main_property};

      if (empty($value)) {
        continue;
      }

      $blazies = $settings['blazies']->reset($settings);
      $blazies->set('delta', $delta)
        ->set('media.input_url', $value);

      $data = ['item' => NULL, 'settings' => $settings];

      if ($entity->getEntityTypeId() == 'media'
            && $entity->hasField($field_name)
            && $entity->get($field_name)->getString() == $value) {
        // We are on the right media entity.
        $media = $entity;
      }
      else {
        // Attempts to fetch media entity.
        $media = $this->formatter
          ->loadByProperties([
            $field_name => $value,
          ], 'media', TRUE);
        $media = reset($media);
      }

      if ($media) {
        $this->blazyOembed->build($data, $media);
      }

      // Media OEmbed with lazyLoad and lightbox supports.
      $build[$delta] = $this->formatter->getBlazy($data, $delta);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $definition = $this->getScopedFormElements();
    $definition['_views'] = isset($form['field_api_classes']);

    $this->admin()->buildSettingsForm($element, $definition);

    // Makes options look compact.
    if (isset($element['background'])) {
      $element['background']['#weight'] = -99;
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'background'        => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
      'responsive_image'  => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if ($field_definition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }

    if ($media_type = $field_definition->getTargetBundle()) {
      $media_type = MediaType::load($media_type);
      return $media_type && $media_type->getSource() instanceof OEmbedInterface;
    }

    return FALSE;
  }

}
