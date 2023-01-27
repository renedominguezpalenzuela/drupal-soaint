<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyFileFormatter is deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Plugin implementation of the 'Blazy File' to get VEF/VEM within images/files.
 *
 * @deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use
 *   \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead.
 * @todo re-check if to keep it as this can be useful for SickBrowser EB.
 * @see https://www.drupal.org/node/3103018
 */
class BlazyFileFormatter extends BlazyFormatterBlazy {

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
  public function buildElement(array &$element, $entity) {
    $this->blazyOembed->build($element, $entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'fieldable_form' => TRUE,
      'multimedia'     => TRUE,
    ] + parent::getPluginScopes();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'file';
  }

}
