<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\Traits\PluginScopesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Trait common for all blazy formatters.
 */
trait BlazyFormatterTrait {

  use PluginScopesTrait;
  use BlazyFormatterViewTrait;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyFormatterManager
   */
  protected $formatter;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * The blazy-related manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $manager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The blazy entity service.
   *
   * @var \Drupal\blazy\BlazyEntityInterface
   */
  protected $blazyEntity;

  /**
   * The blazy oembed service.
   *
   * @var \Drupal\blazy\Media\BlazyOEmbedInterface
   */
  protected $blazyOembed;

  /**
   * Returns the blazy formatter manager.
   */
  public function formatter() {
    return $this->formatter;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Returns any blazy-related manager.
   */
  public function manager() {
    return $this->manager;
  }

  /**
   * Returns the blazy entity manager.
   */
  public function blazyEntity() {
    return $this->blazyEntity;
  }

  /**
   * Returns the blazy oembed manager.
   */
  public function blazyOembed() {
    return $this->blazyOembed;
  }

  /**
   * Returns the blazy admin service.
   */
  public function admin() {
    return \Drupal::service('blazy.admin.formatter');
  }

  /**
   * Injects DI services.
   */
  protected static function injectServices($instance, ContainerInterface $container, $type = '') {
    // Blazy has sequential inheritance, its sub-modules deviate.
    $instance->formatter = $instance->blazyManager = $instance->manager = $container->get('blazy.formatter');

    // Provides optional services.
    if ($type == 'entity') {
      $instance->loggerFactory = $instance->loggerFactory ?? $container->get('logger.factory');
      $instance->blazyEntity = $instance->blazyEntity ?? $container->get('blazy.entity');
      $instance->blazyOembed = $instance->blazyOembed ?? $instance->blazyEntity->oembed();
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->admin()->getSettingsSummary($this->getScopedFormElements());
  }

  /**
   * Builds the settings.
   */
  public function buildSettings() {
    $settings = array_merge($this->getCommonFieldDefinition(), $this->getSettings());
    $blazies  = &$settings['blazies'];
    $is_grid  = !empty($settings['style']) && !empty($settings['grid']);

    $blazies->set('is.grid', $is_grid);

    $this->pluginSettings($blazies, $settings);

    return $settings;
  }

  /**
   * Defines the common scope for both front and admin.
   *
   * @todo convert all these into BlazySettings as well at 3.x after admin
   * updated and sub-modules.
   */
  public function getCommonFieldDefinition() {
    $field = $this->fieldDefinition;
    $settings = [
      'namespace'   => 'blazy',
      'plugin_id'   => $this->getPluginId(),
      'target_type' => $this->getFieldSetting('target_type'),
    ];

    // Exposes few basic formatter settings w/o use_field.
    $data = [
      'label_display' => $this->label,
      'third_party'   => $this->getThirdPartySettings(),
      'view_mode'     => $this->viewMode,
    ];

    foreach (['plugin_id', 'target_type'] as $key) {
      $data[$key] = $settings[$key];
    }

    BlazyField::settings($settings, $data, $field);
    $blazies = $settings['blazies'];

    if (method_exists($this, 'getPluginScopes')
      && $scopes = $this->getPluginScopes()) {
      if (!empty($scopes['target_bundles'])) {
        $blazies->set('field.target_bundles', $scopes['target_bundles']);
      }
    }

    return $settings;
  }

  /**
   * Defines the common scope for the form elements.
   */
  public function getCommonScopedFormElements() {
    return ['settings' => $this->getSettings()] + $this->getCommonFieldDefinition();
  }

  /**
   * Defines the scope for the form elements.
   *
   * Since 2.10 sub-modules can forget this, and use self::getPluginScopes().
   */
  public function getScopedFormElements() {
    // Compat for BVEF till updated to adopt Blazy 2.10 BlazyVideoFormatter.
    $scopes = method_exists($this, 'getPluginScopes')
      ? $this->getPluginScopes() : [];

    // @todo remove `$scopes +` at Blazy 3.x.
    $definitions = $scopes + $this->getCommonScopedFormElements();
    $definitions['scopes'] = $this->toPluginScopes($scopes);
    return $definitions;
  }

}
