<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\blazy\BlazyDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Blazy Grid Text' formatter.
 *
 * @FieldFormatter(
 *   id = "blazy_text",
 *   label = @Translation("Blazy Grid"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class BlazyTextFormatter extends FormatterBase {

  use BlazyFormatterTrait;
  use BlazyFormatterViewBaseTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return self::injectServices($instance, $container, 'text');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::textSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return $this->baseViewElements($items, $langcode);
  }

  /**
   * Build the grid text elements.
   */
  public function buildElements(array &$build, $items) {
    $settings = &$build['settings'];
    $blazies  = $settings['blazies'];

    $blazies->set('is.grid', TRUE)
      ->set('is.unblazy', TRUE)
      ->set('is.text', TRUE)
      ->set('lazy', []);

    $build += $this->getElements($items);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $this->admin()->buildSettingsForm($element, $this->getScopedFormElements());
    return $element;
  }

  /**
   * Returns the Blazy elements, also for sub-modules to re-use.
   */
  protected function getElements($items): array {
    $elements = [];
    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $item) {
      if (empty($item->value)) {
        continue;
      }

      $element = [
        '#type'     => 'processed_text',
        '#text'     => $item->value,
        '#format'   => $item->format,
        '#langcode' => $item->getLangcode(),
      ];

      $elements[] = $element;
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'grid_form'        => TRUE,
      'grid_required'    => TRUE,
      'no_image_style'   => TRUE,
      'no_layouts'       => TRUE,
      'responsive_image' => FALSE,
      'style'            => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

}
