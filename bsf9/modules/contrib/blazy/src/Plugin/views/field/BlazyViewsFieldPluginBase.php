<?php

namespace Drupal\blazy\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\BlazyEntityInterface;
use Drupal\blazy\Traits\PluginScopesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base views field plugin to render a preview of supported fields.
 */
abstract class BlazyViewsFieldPluginBase extends FieldPluginBase {

  use PluginScopesTrait;

  /**
   * The blazy service manager.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The blazy merged settings.
   *
   * @var array
   */
  protected $mergedSettings = [];

  /**
   * Constructs a BlazyViewsFieldPluginBase object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlazyManagerInterface $blazy_manager, BlazyEntityInterface $blazy_entity) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blazyManager = $blazy_manager;
    $this->blazyEntity = $blazy_entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('blazy.manager'), $container->get('blazy.entity'));
  }

  /**
   * Returns the blazy admin.
   */
  public function blazyAdmin() {
    return \Drupal::service('blazy.admin');
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    foreach ($this->getDefaultValues() as $key => $default) {
      $options[$key] = ['default' => $default];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $definitions = $this->getScopedFormElements();

    $form += $this->blazyAdmin()->baseForm($definitions);

    foreach ($this->getDefaultValues() as $key => $default) {
      if (isset($form[$key])) {
        $form[$key]['#default_value'] = $this->options[$key] ?? $default;
        $form[$key]['#weight'] = 0;
        if (in_array($key, ['box_style', 'box_media_style'])) {
          $form[$key]['#empty_option'] = $this->t('- None -');
        }
      }
    }

    if (isset($form['view_mode'])) {
      $form['view_mode']['#description'] = $this->t('Will fallback to this view mode, else entity label.');
    }
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * Defines the default values.
   */
  public function getDefaultValues() {
    return [
      'box_style'       => '',
      'box_media_style' => '',
      'image_style'     => '',
      'media_switch'    => 'media',
      'ratio'           => 'fluid',
      'thumbnail_style' => '',
      'view_mode'       => 'default',
    ];
  }

  /**
   * Merges the settings.
   */
  public function mergedViewsSettings() {
    $settings  = $this->mergedSettings + BlazyDefault::entitySettings();
    $view      = $this->view;
    $view_name = $view->storage->id();
    $view_mode = $view->current_display;
    $plugin_id = $view->style_plugin->getPluginId();
    $display   = $view->style_plugin->displayHandler->getPluginId();
    $instance  = str_replace('_', '-', "{$view_name}-{$display}-{$view_mode}");
    $id        = Blazy::getHtmlId("{$plugin_id}-views-field-{$instance}");
    $count     = count($view->result);

    // Only fetch what we already asked for.
    foreach ($this->getDefaultValues() as $key => $default) {
      $settings[$key] = $this->options[$key] ?? $default;
    }

    // @todo convert some to blazies, and remove tese settings.
    $settings['count'] = $count;
    $settings['view_name'] = $view_name;
    $settings['view_plugin_id'] = $plugin_id;
    $settings['namespace'] = 'blazy';

    $this->blazyManager->preSettings($settings);
    $blazies = $settings['blazies'];

    $view_info = [
      'display'        => $display,
      'instance_id'    => $instance,
      'name'           => $view_name,
      'plugin_id'      => $plugin_id,
      'view_mode'      => $view_mode,
      'is_view'        => TRUE,
      'is_views_field' => TRUE,
    ];

    $blazies->set('count', $count)
      ->set('css.id', $id)
      ->set('namespace', 'blazy')
      ->set('view', $view_info, TRUE);

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'target_type' => !$this->view->getBaseEntityType()
      ? ''
      : $this->view->getBaseEntityType()->id(),
      'thumbnail_style' => TRUE,
    ];
  }

  /**
   * Defines the scope for the form elements.
   *
   * Since 2.10 sub-modules can forget this, and use self::getPluginScopes().
   */
  public function getScopedFormElements() {
    $scopes = $this->getPluginScopes();

    // @todo remove `$scopes +` at Blazy 3.x.
    $definitions = $scopes;
    $definitions['scopes'] = $this->toPluginScopes($scopes);
    $definitions['settings'] = $this->options;
    return $definitions;
  }

}
