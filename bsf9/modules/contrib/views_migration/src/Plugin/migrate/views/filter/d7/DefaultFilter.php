<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Filter plugin.
 *
 * This plugin is used to prepare the Views `filter` display options for
 * migration when no other migrate plugin exists for the current filter plugin.
 *
 * @MigrateViewsFilter(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultFilter extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['table'])) {
      $handler_config['table'] = $this->getViewsHandlerTableMigratePlugin($handler_config['table'])->getNewTableValue($this->infoProvider);
    }
    $this->alterEntityIdField($handler_config);
    $this->alterVocabularySettings($handler_config);
    $this->configurePluginId($handler_config, 'filter');
    $this->alterExposeSettings($handler_config);
    $this->alterOperatorSettings($handler_config);
    $this->alterVocabularySettings($handler_config);
  }

  /**
   * Alter the Filter Handler's "expose" settings.
   *
   * @param array $handler_config
   *   The Filter Handler's configuration to alter.
   */
  protected function alterExposeSettings(array &$handler_config) {
    if (!isset($handler_config['expose'])) {
      return;
    }
    $role_approved = [];
    if (isset($handler_config['expose']['remember_roles'])) {
      // Update User Roles to their new machine names.
      foreach ($handler_config['expose']['remember_roles'] as $rid => $role_data) {
        if (isset($this->userRoles[$rid])) {
          $role_approved[$this->userRoles[$rid]] = $this->userRoles[$rid];
        }
      }
    }
    $handler_config['expose']['remember_roles'] = $role_approved;
  }

  /**
   * Alter the operators settings.
   *
   * @param array $handler_config
   *   The Filter Handler's configuration to alter.
   */
  protected function alterOperatorSettings(array &$handler_config) {
    if (isset($handler_config['plugin_id'])) {
      switch ($handler_config['plugin_id']) {
        case 'boolean':
          $operators = ['=', '!='];
          if (isset($handler_config['operator']) && !in_array($handler_config['operator'], $operators)) {
            $handler_config['operator'] = '=';
            if (is_array($handler_config['value']) && count($handler_config['value'])) {
              $handler_config['value'] = reset($handler_config['value']);
              if ($handler_config['value'] == 'all') {
                $handler_config['value'] = 1;
              }
            }
          }
          elseif (is_array($handler_config['value']) && count($handler_config['value'])) {
            $handler_config['value'] = reset($handler_config['value']);
            if ($handler_config['value'] == 'all') {
              $handler_config['value'] = 1;
            }
          }
          break;

        default:
          // code...
          break;
      }
    }
  }

  /**
   * Alter the Filter Handler's "vocabulary" settings.
   *
   * @param array $handler_config
   *   The Filter Handler's configuration to alter.
   */
  private function alterVocabularySettings(array &$handler_config) {
    if (isset($handler_config['vocabulary'])) {
      $handler_config['plugin_id'] = 'taxonomy_index_tid';
      $handler_config['vid'] = substr($handler_config['vocabulary'], 0, 30);
      // Ensure an empty 'value' setting is an empty array.
      if (array_key_exists('value', $handler_config) && $handler_config['value'] === "") {
        $handler_config['value'] = [];
      }
      unset($handler_config['vocabulary']);
    }
    elseif (isset($handler_config['plugin_id']) && $handler_config['plugin_id'] == 'taxonomy_index_tid') {
      if (!isset($handler_config['vid']) && isset($handler_config['value'])) {
        if (isset($handler_config['value']['min'])) {
          if (isset($handler_config['table'])) {
            $field_map = \Drupal::service('entity_field.manager')->getFieldMap();
            $fc = explode('__', $handler_config['table']);
            if (isset($fc[0]) && isset($fc[1]) && isset($field_map[$fc[0]]) && isset($field_map[$fc[0]][$fc[1]])) {
              $field_config = $field_map[$fc[0]][$fc[1]];
              $fieldConfig = \Drupal::entityTypeManager()->getStorage('field_config');
              $vid = FALSE;
              foreach ($field_config['bundles'] as $bundle) {
                $fco = $fieldConfig->load($fc[0] . '.' . $bundle . '.' . $fc[1]);
                $settings = $fco->getSettings();
                if (isset($settings['target_type']) == 'taxonomy_term') {
                  if (isset($settings['handler_settings']) && isset($settings['handler_settings']['target_bundles'])) {
                    $vid = reset($settings['handler_settings']['target_bundles']);
                    break;
                  }
                }
              }
              $handler_config['vid'] = $vid;
            }
            else {
              $handler_config['remove_this_item'] = TRUE;
            }
          }
        }
        else {
          $database = \Drupal::database();
          $query = $database->select('taxonomy_term_field_data', 'tfd');
          $query->fields('tfd', ['vid']);
          $query->condition('tid', $handler_config['value'], 'IN');
          $handler_config['vid'] = $query->execute()->fetchField();
          $handler_config['vid'] = substr($handler_config['vid'], 0, 30);
        }
      }
    }
  }

}
