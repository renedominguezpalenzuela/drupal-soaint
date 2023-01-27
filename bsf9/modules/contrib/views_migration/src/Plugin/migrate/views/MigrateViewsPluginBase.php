<?php

namespace Drupal\views_migration\Plugin\migrate\views;

use Drupal\Core\Plugin\PluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface;

/**
 * Provides base functionality for Views Migrate plugins.
 */
abstract class MigrateViewsPluginBase extends PluginBase {

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
   * The list of field formatters provided by ViewsMigration.
   *
   * @var array
   *
   * @see \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface::getFormatterList()
   */
  protected $formatterList;

  /**
   * Constructs a Migrate Views plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The Migration plugin.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $this->viewsMigration = $this->getViewsMigrationSourcePlugin($migration);
    $this->pluginList = $this->viewsMigration->getPluginList();
    $this->userRoles = $this->viewsMigration->getUserRoles();
    $this->defaultRelationships = $this->viewsMigration->getDefaultRelationships();
    $this->defaultArguments = $this->viewsMigration->getDefaultArguments();
    $this->viewsData = $this->viewsMigration->d8ViewsData();
    $this->baseTableArray = $this->viewsMigration->baseTableArray();
    $this->entityTableArray = $this->viewsMigration->entityTableArray();
    $this->formatterList = $this->viewsMigration->getFormatterList();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Gets the Views Migration Source Plugin.
   *
   * @param $migration
   *   The Migration plugin.
   *
   * @return \Drupal\views_migration\Plugin\migrate\source\ViewsMigrationInterface
   *   The Views Migration Source Plugin.
   *
   * @throws \LogicException
   *   If the current Migration Source Plugin is not a ViewsMigration object.
   */
  private function getViewsMigrationSourcePlugin($migration) {
    $source_plugin = $migration->getSourcePlugin();
    if (!is_a($source_plugin, ViewsMigrationInterface::class)) {
      throw new \LogicException(sprintf('%s:%s: Unexpected Source Plugin %s.', __METHOD__, __LINE__, get_class($source_plugin)));
    }
    return $source_plugin;
  }

  /**
   * Saves a message related to a source record in the migration message table.
   *
   * @param string $message
   *   The message to record.
   * @param int $level
   *   (optional) The message severity. Defaults to
   *   MigrationInterface::MESSAGE_ERROR.
   */
  protected function saveMessage(string $message, int $level = MigrationInterface::MESSAGE_ERROR) {
    $this->viewsMigration->saveMessage($message, $level);
  }

}
