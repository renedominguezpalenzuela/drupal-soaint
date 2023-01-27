<?php

namespace Drupal\views_migration\Plugin\migrate\source;

use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views\Views;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;

/**
 * The base class for all Views Migration Source plugins.
 */
abstract class BaseViewsMigration extends SqlBase implements ViewsMigrationInterface {

  /**
   * Views migration contains base Table array.
   *
   * @var array
   */
  protected $baseTableArray;

  /**
   * This var entityTableArray based on entity_ids.
   *
   * @var array
   */
  protected $entityTableArray;

  /**
   * Views PluginList.
   *
   * @var array
   */
  protected $pluginList;

  /**
   * Views formatter list.
   *
   * @var array
   */
  protected $formatterList;

  /**
   * User Roles.
   *
   * @var array
   */
  protected $userRoles;

  /**
   * Views Data.
   *
   * @var array
   */
  protected $viewsData;

  /**
   * The relationships on the default display.
   *
   * @var array
   */
  protected $defaultRelationships;

  /**
   * The arguments on the default display.
   *
   * @var array
   */
  protected $defaultArguments;

  /**
   * The machine name of the View being migrated.
   *
   * @var string
   */
  protected $view;

  /**
   * The migration source Row.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * The machine name of the Views Display being prepared.
   *
   * @var string
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);
    $this->baseTableArray = $this->baseTableArray();
    $this->entityTableArray = $this->entityTableArray();
    $this->pluginList = $this->getPluginList();
    $this->formatterList = $this->getFormatterList();
    $this->userRoles = $this->getUserRoles();
    $this->viewsData = $this->d8ViewsData();
    $this->typeMap = $this->getTypeMap();
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      "vid" => $this->t("vid"),
      "name" => $this->t("name"),
      "description" => $this->t("description"),
      "tag" => $this->t("tag"),
      "base_table" => $this->t("base_table"),
      "human_name" => $this->t("human_name"),
      "core" => $this->t("core"),
      "id" => $this->t("id"),
      "display_title" => $this->t("display_title"),
      "display_plugin" => $this->t("display_plugin"),
      "position" => $this->t("position"),
      "display_options" => $this->t("display_options"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  private function getTypeMap() {
    $typeMap = [
      'datetime' => 'datetime',
      'date' => 'datetime',
      'datestamp' => 'timestamp',
    ];
    return $typeMap;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['name']['type'] = 'string';
    $ids['name']['alias'] = 'vv';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getMigration() {
    return $this->migration;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRoles() {
    if (NULL !== $this->userRoles) {
      return $this->userRoles;
    }
    $query = $this->select('role', 'r')->fields('r', ['rid', 'name']);
    $results = $query->execute()->fetchAllAssoc('rid');
    $userRoles = [];
    $map = [
      1 => 'anonymous',
      2 => 'authenticated',
    ];
    foreach ($results as $rid => $role) {
      // Convert source role name to a role machine name using the same process
      // as per \Drupal\Core\Render\Element\MachineName.
      $role_name = preg_replace('/[^a-z0-9_]/', '_', mb_strtolower($role['name']));
      $userRoles[$rid] = $map[$rid] ?? $role_name;
    }
    return $userRoles;
  }

  /**
   * {@inheritdoc}
   */
  public function d8ViewsData() {
    return $this->viewsData ?? \Drupal::service('views.views_data')->getAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginList() {
    if (NULL !== $this->pluginList) {
      return $this->pluginList;
    }
    $plugins = [
      'argument' => 'handler',
      'field' => 'handler',
      'filter' => 'handler',
      'relationship' => 'handler',
      'sort' => 'handler',
      'access' => 'plugin',
      'area' => 'handler',
      'argument_default' => 'plugin',
      'argument_validator' => 'plugin',
      'cache' => 'plugin',
      'display_extender' => 'plugin',
      'display' => 'plugin',
      'exposed_form' => 'plugin',
      'join' => 'plugin',
      'pager' => 'plugin',
      'query' => 'plugin',
      'row' => 'plugin',
      'style' => 'plugin',
      'wizard' => 'plugin',
    ];
    $pluginList = [];
    foreach ($plugins as $pluginName => $value) {
      $pluginNames = $this->fetchPluginNames($pluginName);
      $pluginList[$pluginName] = array_keys($pluginNames);
    }
    return $pluginList;
  }

  /**
   * Fetch plugin instance.
   */
  public static function pluginManager($type) {
    return \Drupal::service('plugin.manager.views.' . $type);
  }

  /**
   * Fetches a list of all base tables available.
   *
   * @param string $type
   *   Either 'display', 'style' or 'row'.
   * @param string|null $key
   *   For style plugins, this is an optional type to restrict to. May be
   *   'normal', 'summary', 'feed' or others based on the needs of the display.
   * @param array $base
   *   An array of possible base tables.
   *
   * @return array
   *   A keyed array of in the form of 'base_table' => 'Description'.
   */
  public function fetchPluginNames(string $type, ?string $key = NULL, array $base = []) {
    $definitions = static::pluginManager($type)->getDefinitions();
    $plugins = [];

    foreach ($definitions as $id => $plugin) {
      // Skip plugins that don't conform to our key, if they have one.
      if ($key && isset($plugin['display_types']) && !in_array($key, $plugin['display_types'], TRUE)) {
        continue;
      }

      if (empty($plugin['no_ui']) && (empty($base) || empty($plugin['base']) || array_intersect($base, $plugin['base']))) {
        $plugins[$id] = $plugin['title'] ?? $id;
      }
    }

    if (!empty($plugins)) {
      asort($plugins);
      return $plugins;
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatterList() {
    if (NULL !== $this->formatterList) {
      return $this->formatterList;
    }
    $formatterManager = \Drupal::service('plugin.manager.field.formatter');
    $formats = $formatterManager->getOptions();
    $return_formats = [];
    $all_formats = [];
    foreach ($formats as $key => $value) {
      $return_formats['field_type'][$key] = array_keys($value);
      $all_formats = array_merge($all_formats, array_keys($value));
    }
    $return_formats['all_formats'] = $all_formats;
    return $return_formats;
  }

  /**
   * {@inheritdoc}
   */
  public function baseTableArray() {
    if (NULL !== $this->baseTableArray) {
      return $this->baseTableArray;
    }
    $baseTableArray = [];
    $entity_list_def = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_list_def as $id => $entity_def) {
      $base_table = $entity_def->get('base_table');
      if (!isset($base_table)) {
        continue;
      }
      $data_table = $entity_def->get('data_table');
      $entity_keys = $entity_def->get('entity_keys');
      // @todo Can this be handled by the CommerceProduct base table plugin and removed from this method?
      if ($base_table == 'commerce_product') {
        $data_table = 'commerce_product_variation_field_data';
        $id = 'commerce_product_variation';
      }
      $baseTableArray[$base_table]['entity_id'] = $id;
      if (!is_null($data_table)) {
        $baseTableArray[$base_table]['data_table'] = $data_table;
      }
      else {
        $baseTableArray[$base_table]['data_table'] = $base_table;
      }
      $baseTableArray[$base_table]['entity_keys'] = $entity_keys;
    }
    return $baseTableArray;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTableArray() {
    if (NULL !== $this->entityTableArray) {
      return $this->entityTableArray;
    }
    $this->entityTableArray = [];
    $entity_list_def = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_list_def as $id => $entity_def) {
      $base_table = $entity_def->get('base_table');
      if (!isset($base_table)) {
        continue;
      }
      $data_table = $entity_def->get('data_table');
      $entity_keys = $entity_def->get('entity_keys');
      // @todo Can this be handled by the CommerceProduct base table plugin and removed from this method?
      if ($base_table == 'commerce_product') {
        $data_table = 'commerce_product_variation_field_data';
        $id = 'commerce_product_variation';
      }
      if (isset($data_table)) {
        $this->entityTableArray[$entity_keys['id']] = [
          'entity_id' => $id,
          'data_table' => $data_table,
          'entity_keys' => $entity_keys,
        ];
      }
      else {
        $this->entityTableArray[$entity_keys['id']] = [
          'entity_id' => $id,
          'data_table' => $base_table,
          'entity_keys' => $entity_keys,
        ];
      }
    }
    return $this->entityTableArray;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsPluginMigratePluginManager($type) {
    return \Drupal::service('plugin.manager.migrate.views.' . $type);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsHandlerMigratePluginManager($type) {
    return \Drupal::service('plugin.manager.migrate.views.' . $type);
  }

  /**
   * Returns the migrate views base table plugin manager.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsBaseTablePluginManagerInterface
   */
  protected function getViewsBaseTableMigratePluginManager() {
    return \Drupal::service('plugin.manager.migrate.views.base_table');
  }

  /**
   * Gets the Migrate Views plugin for the provided source base table.
   *
   * @param string $source_base_table
   *   The base table of the view being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsBaseTableInterface
   *   The plugin responsible for migrating the base table settings for the
   *   provided source base table.
   */
  protected function getViewBaseTableMigratePlugin(string $source_base_table) {
    return $this->getViewsBaseTableMigratePluginManager()->getViewsBaseTableMigratePlugin($this->migration, $source_base_table);
  }

  /**
   * Gets the Views Migrate Plugin for the provided type and source plugin id.
   *
   * @param string $type
   *   The type of migrate plugin to get.
   * @param string|null $source_plugin_id
   *   The source plugin id. If NULL the default migrate plugin will be
   *   returned.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsPluginInterface
   *   The plugin responsible for migrating the Views Plugin for the provided
   *   type and source plugin id.
   */
  protected function getViewsPluginMigratePlugin(string $type, ?string $source_plugin_id) {
    return $this->getViewsPluginMigratePluginManager($type)->getViewsPluginMigratePlugin($this->migration, $source_plugin_id);
  }

  /**
   * Gets the Views Migrate Plugin for the provided type and source handler.
   *
   * @param string $type
   *   The type of migrate plugin to get.
   * @param \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider $info_provider
   *   Provides info about the Views Handler being migrated.
   *
   * @return \Drupal\views_migration\Plugin\MigrateViewsHandlerInterface
   *   The plugin responsible for migrating the Views Handler for the provided
   *   type and handler field value.
   */
  protected function getViewsHandlerMigratePlugin(string $type, SourceHandlerInfoProvider $info_provider) {
    return $this->getViewsHandlerMigratePluginManager($type)->getViewsHandlerMigratePlugin($this->migration, $info_provider);
  }

  /**
   * Gets the Source Handler Info Provider for the Views Handler being migrated.
   *
   * @param $source_data
   *   The current Views Handler source configuration data.
   * @param $display_options
   *   The current Views Display's display options.
   * @param $base_entity_type
   *   The View's base entity type.
   *
   * @return \Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider
   *   The Source Handler Info Provider for the Views Handler being migrated.
   */
  protected function getSourceHandlerInfoProvider($source_data, $display_options, $base_entity_type) {
    if (SourceHandlerEntityFieldInfoProvider::isEntityField($source_data)) {
      return new SourceHandlerEntityFieldInfoProvider($source_data, $display_options, $base_entity_type, $this);
    }
    return new SourceHandlerInfoProvider($source_data, $display_options, $base_entity_type, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRelationships() {
    return $this->defaultRelationships;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultArguments() {
    return $this->defaultArguments;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessage(string $message, int $level = MigrationInterface::MESSAGE_ERROR) {
    $message = sprintf('VIEW: %s DISPLAY: %s - ', $this->view, $this->display) . $message;
    $this->idMap->saveMessage($this->row->getSourceIdValues(), $message, $level);
  }

  /**
   * ViewsMigration convertDisplayPlugins.
   *
   * @param array $display_options
   *   Views display options.
   *
   * @return array
   */
  protected function convertDisplayPlugins(array $display_options) {
    $plugin_types = [
      'query' => $display_options['query']['type'] ?? NULL,
      'access' => $display_options['access']['type'] ?? NULL,
      'cache' => $display_options['cache']['type'] ?? NULL,
      'exposed_form' => $display_options['exposed_form']['type'] ?? NULL,
      'pager' => $display_options['pager']['type'] ?? NULL,
      'row' => $display_options['row_plugin'] ?? NULL,
      'style' => $display_options['style_plugin'] ?? NULL,
    ];
    foreach ($plugin_types as $plugin_type => $source_plugin_id) {
      $this->getViewsPluginMigratePlugin($plugin_type, $source_plugin_id)->prepareDisplayOptions($display_options);
    }
    if (isset($display_options['menu'])) {
      $menu_name_map = [
        'main-menu' => 'main',
        'management' => 'admin',
        'navigation' => 'tools',
        'user-menu' => 'account',
      ];
      if (isset($menu_name_map[$display_options['menu']['name']])) {
        $display_options['menu']['name'] = $menu_name_map[$display_options['menu']['name']];
      }
      if (isset($display_options['menu']['name'])) {
        $display_options['menu']['menu_name'] = $display_options['menu']['name'];
      }
      else {
        $display_options['menu']['menu_name'] = 'tools';
      }
    }
    if (isset($display_options['metatags'])) {
      $this->prepareMetatags($display_options);
    }
    return $display_options;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareMetatags(array &$display_options) {
    $keyMap = [
      'canonical' => 'canonical_url',
      'content-language' => 'content_language',
      'geo.position' => 'geo_position',
      'geo.placename' => 'geo_placename',
      'geo.region' => 'geo_region',
      'original-source' => 'original_source',
      'revisit-after' => 'revisit_after',
      'cache-control' => 'cache_control',
    ];
    if (isset($display_options['metatags'])) {
      $metatagsNew = [];
      foreach ($display_options['metatags'] as $langCode => $metatags) {
        foreach ($metatags as $key => $value) {
          if (isset($keyMap[$key])) {
            $key = $keyMap[$key];
          }
          if (is_array($value['value'])) {
            $value = implode(',', array_keys($value['value']));
          }
          else {
            $value = $value['value'];
          }
          $metatagsNew[$key] = $value;
        }
      }
      $display_options['display_extenders']['metatag_display_extender'] = [
        'metatags' => $metatagsNew,
        'tokenize' => TRUE,
      ];

      unset($display_options['metatags']);
    }
  }

  /**
   * ViewsMigration convertHandlerDisplayOptions.
   *
   * @param array $display_options
   *   Views display options.
   * @param string $entity_type
   *   Views base entity type.
   */
  protected function convertHandlerDisplayOptions(array $display_options, string $entity_type) {
    $option_handler_map = [
      'relationships' => 'relationship',
      'sorts' => 'sort',
      'filters' => 'filter',
      'arguments' => 'argument',
      'fields' => 'field',
      'header' => 'area',
      'footer' => 'area',
      'empty' => 'area',
    ];
    $tableMap = [
      'node' => 'node_field_data',
      'term_data' => 'taxonomy_term_field_data',
      'term_hierarchy' => 'taxonomy_term__parent',
      'term_node' => 'taxonomy_index',
      'node_revision' => 'node_field_revision',
    ];
    $types = [
      'yes-no', 'default', 'true-false', 'on-off', 'enabled-disabled',
      'boolean', 'unicode-yes-no', 'custom',
    ];
    foreach ($option_handler_map as $option => $handler) {
      if (!isset($display_options[$option])) {
        continue;
      }
      $fields = $display_options[$option];
      foreach ($fields as $key => $data) {
        $this->getViewsHandlerMigratePlugin($handler, $this->getSourceHandlerInfoProvider($data, $display_options, $entity_type))->alterHandlerConfig($data);
        if (is_array($data) && (isset($data['php_output']) || isset($data['remove_this_item']))) {
          unset($display_options[$option][$key]);
          continue;
        }
        switch ($data['field']) {
          case 'views_bulk_operations':
            $fields[$key]['plugin_id'] = 'views_bulk_operations_bulk_form';
            $fields[$key]['table'] = 'views';
            $fields[$key]['field'] = 'views_bulk_operations_bulk_form';
            break;

          case 'operations':
            $fields[$key]['plugin_id'] = 'entity_operations';
            $fields[$key]['entity_type'] = $entity_type;
            $baseTable = \Drupal::entityTypeManager()->getStorage($entity_type)->getBaseTable();
            $fields[$key]['table'] = $baseTable;
            break;

          default:
            // code...
            break;
        }
        if (isset($data['field'])) {
          $types = [
            'view_node', 'edit_node', 'delete_node', 'cancel_node', 'view_user', 'view_comment', 'edit_comment', 'delete_comment', 'approve_comment', 'replyto_comment', 'comment', 'comment_count', 'last_comment_timestamp', 'last_comment_uid', 'last_comment_name',
          ];
          $table_map = [
            'views_entity_node' => 'node',
            'users' => 'users',
            'comment' => 'comment',
            'node_comment_statistics' => 'comment_entity_statistics',
          ];
          if (in_array($data['field'], $types)) {
            $fields[$key]['table'] = $table_map[$data['table']];
          }
          if (isset($this->viewsData[$entity_type][$data['field']])) {
            $fields[$key]['table'] = $entity_type;
            $fields[$key]['plugin_id'] = $this->viewsData[$entity_type][$data['field']][$option]['id'];
          }
          if (isset($table_map[$data['table']])) {
            $fields[$key]['table'] = $table_map[$data['table']];
          }
        }
        $display_options[$option][$key] = $data;
      }
      if ($this->display === 'default') {
        if ($option === 'relationships') {
          $this->defaultRelationships = $display_options['relationships'];
        }
        elseif ($option === 'arguments') {
          $this->defaultArguments = $display_options['arguments'];
        }
      }
    }
    return $display_options;
  }

  /**
   * ViewsMigration convertHandlerDisplayOptions.
   *
   * @param array $display_options
   *   Views display options.
   * @param string $entity_type
   *   Views base entity type.
   */
  protected function checkHandlerDisplayRelationships(array &$display_options, string $entity_type, array $masterDisplay, string $viewName, string $displayId) {
    $option_handler_map = [
      'relationships' => 'relationship',
      'sorts' => 'sort',
      'filters' => 'filter',
      'arguments' => 'argument',
      'fields' => 'field',
      'header' => 'area',
      'footer' => 'area',
      'empty' => 'area',
    ];
    $relationships = [];
    if (isset($display_options['relationships']) && is_array($display_options['relationships']) && count($display_options['relationships'])) {
      $relationships = $display_options['relationships'];
    }
    else {
      if (isset($masterDisplay['relationships']) && is_array($masterDisplay['relationships'])) {
        $relationships = $masterDisplay['relationships'];
      }
    }
    $relationship_keys = array_keys($relationships);
    foreach ($option_handler_map as $option => $handler) {
      if (!isset($display_options[$option])) {
        continue;
      }
      $fields = $display_options[$option];
      foreach ($fields as $key => $data) {
        if (isset($data['relationship']) && $data['relationship'] != 'none') {
          if (!in_array($data['relationship'], $relationship_keys)) {
            echo "\n -> \033[32m D7 Views broken - [View : {$viewName}] - [Display : {$displayId}] \033[0m - \033[1m{$option} -> {$key} -> Relationship ({$data['relationship']}) Not found \033[0m\n";
          }
          else {
            if (!isset($display_options['relationships'][$data['relationship']])) {
              echo "\n -> \033[32m D7 Views broken - [View : {$viewName}] - [Display : {$displayId}] \033[0m - \033[1m{$option} -> {$key} -> Relationship ({$data['relationship']}) Not found in display.";
              if (isset($masterDisplay['relationships'][$data['relationship']])) {
                echo "But found in default display.";
                $display_options['relationships'][$data['relationship']] = $masterDisplay['relationships'][$data['relationship']];
              }
              echo "\033[0m\n";
            }
          }
        }
      }
    }
  }

  /**
   * ViewsMigration removeNonExistFields.
   *
   * @param array $display_options
   *   Views display options.
   */
  protected function removeNonExistFields(array $display_options) {
    $options = [
      'fields',
      'filters',
      'arguments',
      'relationships',
      'sorts',
      'footer',
      'empty',
    ];
    $available_views_tables = array_keys($this->viewsData);
    foreach ($options as $option) {
      if (isset($display_options[$option])) {
        foreach ($display_options[$option] as $field_id => $field) {
          if (!in_array($field['table'], $available_views_tables, TRUE)) {
            $message = sprintf("The field %s does not exist and has been removed from the %s configuration.", $field_id, $option);
            $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
            unset($display_options[$option][$field_id]);
          }
        }
      }
    }
    return $display_options;
  }

  /**
   * Add Views Migration log message for each broken Views Handlers/Plugins.
   *
   * @param array $display_options
   *   The display options for the current Views display.
   */
  protected function logBrokenHandlers(array $display_options) {
    $handlers = Views::getHandlerTypes();
    foreach ($handlers as $type => $info) {
      if (!empty($display_options[$info['plural']])) {
        foreach ($display_options[$info['plural']] as $field_name => $field) {
          $handler_type = $info['type'] ?? $type;
          $plugin = Views::handlerManager($handler_type)->getHandler($field)->getPluginId();
          if ($plugin === 'broken') {
            $message = sprintf("The '%s' %s Plugin is missing and will be listed as broken.", $field_name, $type);
            $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
          }
        }
      }
    }
  }

}
