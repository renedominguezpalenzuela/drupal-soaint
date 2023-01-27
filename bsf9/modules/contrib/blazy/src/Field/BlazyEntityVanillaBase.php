<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\blazy\Blazy;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;

/**
 * Base class for entity reference formatters without field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityMediaBase
 */
abstract class BlazyEntityVanillaBase extends EntityReferenceFormatterBase {

  // Since 2.9 Blazy adapts to sub-module self::viewElements() to DRY so they
  // can remove their own FormatterViewTrait later thanks to similarities.
  use BlazyFormatterTrait {
    pluginSettings as traitPluginSettings;
  }

  /**
   * Returns media contents.
   */
  public function buildElements(array &$build, $entities, $langcode) {
    foreach ($entities as $delta => $entity) {
      // Protect ourselves from recursive rendering.
      static $depth = 0;
      $depth++;
      if ($depth > 20) {
        $this->loggerFactory->get('entity')
          ->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '@entity_id' => $entity->id(),
          ]);
        return $build;
      }

      $this->prepareElement($build, $entity, $langcode, $delta);

      // Add the entity to cache dependencies so to clear when it is updated.
      if (!empty($build['items'][$delta])) {
        $this->formatter
          ->getRenderer()
          ->addCacheableDependency($build['items'][$delta], $entity);
      }

      $depth = 0;
    }
  }

  /**
   * Build item contents.
   */
  public function buildElement(array &$build, $entity, $langcode) {
    $settings  = $build['settings'];
    $view_mode = $settings['view_mode'] ?? 'full';

    // Sub-modules always flag `vanilla` as required, -- configurable, or not.
    // The "paragraphs_type" entity type did not specify a view_builder handler.
    if (!empty($settings['vanilla'])) {
      $manager = $this->formatter->getEntityTypeManager();
      $type = $entity->getEntityTypeId();

      if ($manager->hasHandler($type, 'view_builder')) {
        $build['items'][] = $manager
          ->getViewBuilder($type)
          ->view($entity, $view_mode, $langcode);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);

    // @todo remove after sub-modules.
    $definition['view_mode'] = $this->viewMode;
    $definition['plugin_id'] = $this->getPluginId();
    $definition['target_type'] = $this->getFieldSetting('target_type');

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * Returns available bundles.
   */
  protected function getAvailableBundles(): array {
    $target_type = $this->getFieldSetting('target_type');
    $views_ui    = $this->getFieldSetting('handler') == 'default';
    $bundles     = $views_ui
      ? [] : $this->getFieldSetting('handler_settings')['target_bundles'];

    // Fix for Views UI not recognizing Media bundles, unlike Formatters.
    if (empty($bundles)
      && $service = Blazy::service('entity_type.bundle.info')) {
      $bundles = $service->getBundleInfo($target_type);
    }

    return $bundles;
  }

  /**
   * Returns fields as options. Passing empty array will return them all.
   */
  protected function getFieldOptions(array $names = [], $target_type = NULL): array {
    $target_type = $target_type ?: $this->getFieldSetting('target_type');
    $bundles     = $this->getAvailableBundles();

    return $this->admin()->getFieldOptions($bundles, $names, $target_type);
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
   * Prepare item contents.
   *
   * Alternative for self::buildElement() with extra params for convenient.
   */
  protected function prepareElement(array &$build, $entity, $langcode, $delta): void {
    $settings = $build['settings'];
    $blazies  = $settings['blazies']->reset($settings);
    $bundle   = $entity->bundle();

    // @todo remove after sub-modules.
    $settings['delta'] = $delta;
    $settings['langcode'] = $langcode;

    $blazies->set('bundles.' . $bundle, $bundle, TRUE)
      ->set('language.code', $langcode)
      ->set('delta', $delta);

    $build['settings'] = $settings;
    $this->buildElement($build, $entity, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'no_layouts'       => TRUE,
      'no_image_style'   => TRUE,
      'responsive_image' => FALSE,
      'target_bundles'   => $this->getAvailableBundles(),
      'vanilla'          => TRUE,
      'view_mode'        => $this->viewMode,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    $this->traitPluginSettings($blazies, $settings);
    $blazies->set('is.blazy', TRUE);

    // @todo remove.
    $settings['blazy'] = TRUE;
  }

}
