<?php

namespace Drupal\views_migration\Plugin\migrate\views;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface;

/**
 * Provides information about the Views Handler being migrated.
 */
class SourceHandlerInfoProvider {

  /**
   * The source configuration for the Views handler.
   *
   * @var array
   */
  protected $sourceData;

  /**
   * The Views Display's display options.
   *
   * @var array
   */
  protected $displayOptions;

  /**
   * The View's base entity type.
   *
   * @var string
   */
  protected $viewBaseEntityType;

  /**
   * The Views Migration plugin.
   *
   * @var \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface
   */
  protected $viewsMigration;

  /**
   * Views PluginList provided by ViewsMigration.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::getPluginList()
   */
  protected $pluginList;

  /**
   * User Roles provided by ViewsMigration.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::getUserRoles()
   */
  protected $userRoles;

  /**
   * The relationships declared on the default display.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::getDefaultRelationships()
   */
  protected $defaultRelationships;

  /**
   * The arguments declared on the default display.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::getDefaultArguments()
   */
  protected $defaultArguments;

  /**
   * The Views Data provided by ViewsMigration.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::d8ViewsData()
   */
  protected $viewsData;

  /**
   * The Base Table array provided by ViewsMigration.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::baseTableArray()
   */
  protected $baseTableArray;

  /**
   * The Entity Table array provided by ViewsMigration.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::entityTableArray()
   */
  protected $entityTableArray;

  /**
   * Constructs a SourceHandlerInfoProvider object.
   *
   * @param array $source_data
   *   The source configuration data for the Views Handler being migrated.
   * @param array $display_options
   *   The display options for the View Display being migrated.
   * @param string $base_entity_type
   *   The base entity type for the View being migrated.
   * @param \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface $views_migration
   *   The Views Migration plugin.
   */
  public function __construct(array $source_data, array $display_options, string $base_entity_type, ViewsMigrationInterface $views_migration) {
    $this->sourceData = $source_data;
    $this->displayOptions = $display_options;
    $this->viewBaseEntityType = $base_entity_type;
    $this->viewsMigration = $views_migration;
    $this->pluginList = $this->viewsMigration->getPluginList();
    $this->userRoles = $this->viewsMigration->getUserRoles();
    $this->defaultRelationships = $this->viewsMigration->getDefaultRelationships();
    $this->defaultArguments = $this->viewsMigration->getDefaultArguments();
    $this->viewsData = $this->viewsMigration->d8ViewsData();
    $this->baseTableArray = $this->viewsMigration->baseTableArray();
    $this->entityTableArray = $this->viewsMigration->entityTableArray();
  }

  /**
   * Gets the source configuration data for the Views handler being migrated.
   *
   * @return array
   *   The source configuration data for the Views handler being migrated.
   */
  public function getSourceData() {
    return $this->sourceData;
  }

  /**
   * Gets the base entity type for the View being migrated.
   *
   * @return string
   */
  public function getViewBaseEntityType() {
    return $this->viewBaseEntityType;
  }

  /**
   * Gets the display options or the current display.
   *
   * @return array
   */
  public function getDisplayOptions() {
    return $this->displayOptions;
  }

  /**
   * Determines if the Views Field provided represents an entity id.
   *
   * @param string $field
   *   The field to check.
   *
   * @return bool
   */
  public function isEntityIdField(string $field) {
    $entity_ids = [
      '_tid',
      '_uid',
      '_nid',
      '_fid',
    ];
    $entity_id_check = mb_substr($field, -4);
    return in_array($entity_id_check, $entity_ids, TRUE);
  }

  /**
   * Determines if the Views Field provided represents an entity id.
   *
   * @param string $field
   *   The field to check.
   *
   * @return bool
   */
  public function isEntityReferenceField(array &$handler_config) {
    $entityType = $this->getRelationshipEntityType($handler_config);
    $fieldStorageConfig = FieldStorageConfig::loadByName($entityType, $handler_config['field']);
    if ($fieldStorageConfig) {
      $target_type = $fieldStorageConfig->getSetting('target_type');
      $handler_config['target_type'] = $target_type;
      if ($target_type) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determine the entity type of the provided Relationship Handler.
   *
   * @param array $relationship
   *   The Relationship Handler configuration array to get the entity type for.
   *
   * @return string
   *   The entity type of the provided Relationship Handler.
   */
  public function getRelationshipEntityType(array $relationship): string {
    // If the relationship does not have its own relationship, its entity type
    // is the same as the base entity type.
    if (empty($relationship['relationship']) || $relationship['relationship'] === 'none') {
      return $this->viewBaseEntityType;
    }
    $display_relationships = $this->displayOptions['relationships'] ?? [];
    // Get entity type of the nested relationship;.
    $nested_relationship = $display_relationships[$relationship['relationship']] ?? $this->defaultRelationships[$relationship['relationship']] ?? NULL;
    if (NULL === $nested_relationship) {
      $message = sprintf("The relationship key %s does not exist on the the current or the default display. Using the base view entity type %s", $relationship['relationship'], $this->viewBaseEntityType);
      $this->viewsMigration->saveMessage($message);
      return $this->viewBaseEntityType;
    }
    if (isset($nested_relationship['entity_type'])) {
      return $nested_relationship['entity_type'];
    }
    if (isset($this->viewsData[$nested_relationship['table']][$nested_relationship['field']]['relationship']['entity type'])) {
      return $this->viewsData[$nested_relationship['table']][$nested_relationship['field']]['relationship']['entity type'];
    }
    $message = sprintf("Unable to determine the entity type for the relationship %s. Using the base view entity type %s", $relationship['id'], $this->viewBaseEntityType);
    $this->viewsMigration->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
    return $this->viewBaseEntityType;
  }

  /**
   * Gets the Field Config entity for the provided entity field.
   *
   * @param string $entity_type
   *   The entity type of the field to get the Field Config for.
   * @param string $field_name
   *   The field to get the Field Config for.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig|null
   */
  public function getFieldConfig(string $entity_type, string $field_name) {
    return FieldStorageConfig::loadByName($entity_type, $field_name);
  }

  /**
   * Gets the target entity type for a provided entity reference field.
   *
   * @param string $entity_type
   *   The entity type of the field to get the target entity type for.
   * @param string $reference_field_name
   *   The field to get the target entity type for.
   *
   * @return string|null
   */
  protected function getReferenceFieldTargetType(string $entity_type, string $reference_field_name) {
    $config = $this->getFieldConfig($entity_type, $reference_field_name);
    if (NULL === $config) {
      return NULL;
    }
    return $config->getSetting('target_type');
  }

}
