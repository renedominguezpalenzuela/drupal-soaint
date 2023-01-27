<?php

namespace Drupal\views_migration\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\source\BaseViewsMigration;

/**
 * Drupal 7 views source from database.
 *
 * @MigrateSource(
 *   id = "d7_views_migration",
 *   source_module = "views"
 * )
 */
class ViewsMigration extends BaseViewsMigration {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('views_view', 'vv')
      ->fields('vv', [
        'vid', 'name', 'description', 'tag', 'base_table', 'human_name', 'core',
      ]);
    return $query;
  }

  /**
   * ViewsMigration prepareRow.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration source ROW.
   */
  public function prepareRow(Row $row) {
    $this->row = $row;
    $viewId = $row->getSourceProperty('name');
    $this->view = strtolower($viewId);
    $vid = $row->getSourceProperty('vid');
    $base_table = $row->getSourceProperty('base_table');
    $base_table_plugin = $this->getViewBaseTableMigratePlugin($base_table);
    $base_table = $base_table_plugin->getNewBaseTable($base_table);
    $available_views_tables = array_keys($this->viewsData);

    try {
      if (!in_array($base_table, $available_views_tables)) {
        if (php_sapi_name() === 'cli') {
          echo "\n\033[32m [Ignored]\033[0m - \033[1mThe view ({$viewId}) base table ({$base_table}) is not exist in your database.\033[0m";
        }
        throw new MigrateSkipRowException('The views base table ' . $base_table . ' is not exist in your database.');
      }
    }
    catch (MigrateSkipRowException $e) {
      $skip = TRUE;
      $save_to_map = $e->getSaveToMap();
      if ($message = trim($e->getMessage())) {
        $this->idMap->saveMessage($row->getSourceIdValues(), $message, MigrationInterface::MESSAGE_INFORMATIONAL);
      }
      if ($save_to_map) {
        $this->idMap->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
        $this->currentRow = NULL;
        $this->currentSourceIds = NULL;
      }
      return FALSE;
    }
    $query = $this->select('views_display', 'vd')
      ->fields('vd', [
        'id', 'display_title', 'display_plugin', 'display_options', 'position',
      ]);
    $query->condition('vid', $vid);
    $execute = $query->execute();
    $this->defaultRelationships = [];
    $this->defaultArguments = [];
    $display = [];
    $row->setSourceProperty('base_table', $base_table_plugin->getNewSourceBaseTable($base_table));
    $row->setSourceProperty('base_field', $base_table_plugin->getNewSourceBaseField($base_table));
    $entity_type = $base_table_plugin->getBaseTableEntityType($base_table);
    $source_displays = [];
    // Prepare displays for processing. Ensure the "default" display is the
    // first display processed so that its configuration can be used when
    // processing other displays.
    while ($result = $execute->fetchAssoc()) {
      if ($result['id'] === 'default') {
        array_unshift($source_displays, $result);
        continue;
      }
      $source_displays[] = $result;
    }
    // Prepare the options for all displays.
    $masterDisplay = [];
    foreach ($source_displays as $source_display) {
      $display_options = $source_display['display_options'];
      $id = strtolower($source_display['id']);
      $this->display = $id;
      $display_options = unserialize($display_options);
      $display[$id]['display_plugin'] = $source_display['display_plugin'];
      $display[$id]['id'] = $source_display['id'];
      $display[$id]['display_title'] = $source_display['display_title'];
      $display[$id]['position'] = $source_display['position'];
      $display[$id]['display_options'] = $display_options;
      if (isset($source_display['display_plugin'])) {
        $this->getViewsPluginMigratePlugin('display', $source_display['display_plugin'])->prepareDisplayOptions($display[$id]);
      }
      $display[$id]['display_options'] = $this->convertDisplayPlugins($display[$id]['display_options']);
      $display[$id]['display_options'] = $this->convertHandlerDisplayOptions($display[$id]['display_options'], $entity_type);
      $this->checkHandlerDisplayRelationships($display[$id]['display_options'], $entity_type, $masterDisplay, $viewId, $id);
      $display[$id]['display_options'] = $this->removeNonExistFields($display[$id]['display_options']);
      $this->logBrokenHandlers($display[$id]['display_options']);
      $this->display = NULL;
      if ($id == 'default') {
        $masterDisplay = $display[$id]['display_options'];
      }
    }
    $row->setSourceProperty('display', $display);
    $this->row = NULL;
    $this->view = NULL;
    return parent::prepareRow($row);
  }

}
