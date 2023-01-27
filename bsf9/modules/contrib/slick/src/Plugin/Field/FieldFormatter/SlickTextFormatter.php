<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyTextFormatter;
use Drupal\slick\SlickDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Slick Text' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_text",
 *   label = @Translation("Slick Text"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {"editor" = "disabled"}
 * )
 */
class SlickTextFormatter extends BlazyTextFormatter {

  use SlickFormatterTrait {
    buildSettings as traitBuildSettings;
  }

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
    return SlickDefault::baseSettings() + SlickDefault::gridSettings();
  }

  /**
   * Build the slick carousel elements.
   */
  public function buildElements(array &$build, $items) {
    foreach ($this->getElements($items) as $element) {
      $build['items'][] = $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    return ['vanilla' => TRUE] + $this->traitBuildSettings();
  }

  /**
   * Defines the scope for the form elements.
   */
  public function getScopedFormElements() {
    return [
      'grid_form'        => TRUE,
      'no_image_style'   => TRUE,
      'no_layouts'       => TRUE,
      'responsive_image' => FALSE,
      'style'            => TRUE,
    ] + $this->getCommonScopedFormElements();
  }

}
