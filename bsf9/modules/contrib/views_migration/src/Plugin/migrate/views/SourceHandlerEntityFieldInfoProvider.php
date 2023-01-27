<?php

namespace Drupal\views_migration\Plugin\migrate\views;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface;

/**
 * Provides information about a Views Handler that represents an Entity Field.
 */
class SourceHandlerEntityFieldInfoProvider extends SourceHandlerInfoProvider {

  /**
   * Constructs a SourceHandlerEntityFieldInfoProvider object.
   *
   * @param array $source_data
   *   The source configuration data for the Views Handler being migrated.
   * @param array $display_options
   *   The display options for the View Display being migrated.
   * @param string $base_entity_type
   *   The base entity type for the View being migrated.
   * @param \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface $views_migration
   *   The Views Migration plugin.
   *
   * @throws \LogicException
   *   If the Views Handler does not represent an Entity Field.
   */
  public function __construct(array $source_data, array $display_options, string $base_entity_type, ViewsMigrationInterface $views_migration) {
    if (!static::isEntityField($source_data)) {
      throw new \LogicException(sprintf("%s:%s: The Handler's Table value '%s' does not represent an Entity Field. Therefore, this class should not be used.", __METHOD__, __LINE__, $this->getSourceData()['table']));
    }
    parent::__construct($source_data, $display_options, $base_entity_type, $views_migration);
  }

  /**
   * Determine if the Views Handler represents an Entity Field.
   *
   * @param array $source_data
   *   The source configuration data for the Views Handler being migrated.
   *
   * @return bool
   */
  public static function isEntityField(array $source_data) {
    return mb_strpos($source_data['table'], 'field_data') === 0;
  }

  /**
   * Gets the Field Type for the Entity Field that the View Handler represents.
   *
   * @return string
   *   The Field Type for the Entity Field that the View Handler represents.
   */
  public function getEntityFieldType() {
    $entityType = $this->getFieldEntityType();
    if ($entityType == NULL) {
      return '';
    }
    $field_config = $this->getFieldConfig($entityType, $this->getEntityFieldName());
    if (NULL === $field_config) {
      return '';
    }
    return $field_config->getType();
  }

  /**
   * Determines the Field's Entity Type.
   *
   * @return string
   *   The Field's Entity Type.
   */
  public function getFieldEntityType() {
    if (!isset($this->sourceData['relationship'])) {
      return $this->viewBaseEntityType;
    }
    return $this->getFieldEntityTypeFromRelationship($this->sourceData['relationship']);
  }

  /**
   * Gets the Field's machine name.
   *
   * @return string
   *   The Field's machine name.
   */
  public function getEntityFieldName() {
    return substr($this->sourceData['table'], 11);
  }

  /**
   * Determines the Field's Entity Type from its relationship.
   *
   * @param string $relationship_id
   *   The Field's relationship id.
   *
   * @return string
   */
  private function getFieldEntityTypeFromRelationship(string $relationship_id) {
    if ($relationship_id === 'none' || empty($relationship_id)) {
      return $this->viewBaseEntityType;
    }
    $relationship = $this->displayOptions['relationships'][$relationship_id] ?? $this->defaultRelationships[$relationship_id] ?? NULL;
    if (NULL === $relationship) {
      $message = sprintf("The relationship key %s does not exist on the the current or the default display.", $relationship_id);
      $this->viewsMigration->saveMessage($message);
      return $this->viewBaseEntityType;
    }
    // If this is a reverse relationship, get the entity type from the
    // relationship field.
    if (isset($relationship['entity_type'])) {
      return $relationship['entity_type'];
    }
    if (!empty($relationship['entity_type']) && mb_strpos($relationship['field'], 'reverse_') === 0) {
      $field_parts = explode('__', $relationship['field']);
      return $field_parts[1];
    }
    if (!empty($this->entityTableArray[$relationship['field']]['entity_id'])) {
      return $this->entityTableArray[$relationship['field']]['entity_id'];
    }
    $relation_entity_type = $this->getRelationshipEntityType($relationship);
    $entity_reference_target = $this->getReferenceFieldTargetType($relation_entity_type, $relationship['field']);
    if (NULL !== $entity_reference_target) {
      return $entity_reference_target;
    }
    $message = sprintf("Unable to determine the entity type for the relationship %s. Using the base view entity type %s", $relationship['id'], $this->viewBaseEntityType);
    $this->viewsMigration->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
    return $this->viewBaseEntityType;
  }

}
