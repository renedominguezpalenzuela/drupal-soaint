<?php

namespace Drupal\blazy;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\blazy\Cache\BlazyCache;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\BlazyResponsiveImage;
use Drupal\blazy\Utility\Check;
use Drupal\blazy\Utility\Path;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common shared methods across Blazy ecosystem to DRY.
 *
 * @todo extends BlazyBase at or by 3.x, and remove most non-media methods.
 */
abstract class BlazyManagerBase implements BlazyManagerInterface {

  // Fixed for EB AJAX issue: #2893029.
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The app root.
   *
   * @var \SplString
   */
  protected $root;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The cached data.
   *
   * @var array
   */
  protected $cachedData;

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct($root, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->root              = $root;
    $this->entityRepository  = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      Blazy::root($container),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );

    // @todo remove and use DI at 2.x+ post sub-classes updates.
    $instance->setLanguageManager($container->get('language_manager'));
    return $instance;
  }

  /**
   * Returns the app root.
   */
  public function root() {
    return $this->root;
  }

  /**
   * Returns the language manager service.
   */
  public function languageManager() {
    return $this->languageManager;
  }

  /**
   * Sets the language manager service.
   *
   * @todo remove and use DI at 3.x+ post sub-classes updates.
   */
  public function setLanguageManager($language_manager) {
    $this->languageManager = $language_manager;
    return $this;
  }

  /**
   * Returns the entity repository service.
   */
  public function getEntityRepository() {
    return $this->entityRepository;
  }

  /**
   * Returns the entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Returns the module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer.
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * Returns the config factory.
   */
  public function getConfigFactory() {
    return $this->configFactory;
  }

  /**
   * Returns the cache.
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * Returns any config, or keyed by the $setting_name.
   */
  public function configLoad($setting_name = '', $settings = 'blazy.settings') {
    $config  = $this->configFactory->get($settings);
    $configs = $config->get();
    unset($configs['_core']);
    return empty($setting_name) ? $configs : $config->get($setting_name);
  }

  /**
   * Returns a shortcut for entity type storage.
   */
  public function getStorage($type = 'media') {
    return $this->entityTypeManager->getStorage($type);
  }

  /**
   * Returns the entity query object for this entity type.
   */
  public function entityQuery($type, $conjunction = 'AND') {
    return $this->getStorage($type)->getQuery($conjunction);
  }

  /**
   * Returns a shortcut for loading entity by its properties.
   *
   * The only difference from EntityStorageBase::loadByProperties() is the
   * explicit access TRUE specific for content entities, FALSE config ones.
   *
   * @see https://www.drupal.org/node/3201242
   */
  public function loadByProperties(
    array $values,
    $type = 'file',
    $access = TRUE,
    $conjunction = 'AND',
    $condition = 'IN'
  ): array {
    $storage = $this->getStorage($type);
    $query = $storage->getQuery($conjunction);

    $query->accessCheck($access);
    $this->buildPropertyQuery($query, $values, $condition);

    $result = $query->execute();
    return $result ? $storage->loadMultiple($result) : [];
  }

  /**
   * Returns a shortcut for loading entity by its UUID.
   */
  public function loadByUuid($uuid, $type = 'file') {
    return $this->entityRepository->loadEntityByUuid($type, $uuid);
  }

  /**
   * Returns a shortcut for loading a config entity: image_style, slick, etc.
   */
  public function entityLoad($id, $type = 'image_style') {
    return $this->getStorage($type)->load($id);
  }

  /**
   * Returns a shortcut for loading multiple configuration entities.
   */
  public function entityLoadMultiple($type = 'image_style', $ids = NULL) {
    return $this->getStorage($type)->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []) {
    $load = [];
    Check::attachments($load, $attach);

    $this->moduleHandler->alter('blazy_attach', $load, $attach);
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedData(
    $cid,
    array $data = [],
    $reset = FALSE,
    $alter = NULL,
    array $context = []
  ): array {
    if (!isset($this->cachedData[$cid]) || $reset) {
      $cache = $this->cache->get($cid);
      if ($cache && $result = $cache->data) {
        $this->cachedData[$cid] = $result;
      }
      else {
        // Allows empty array to trigger hook_alter.
        if (is_array($data)) {
          $this->moduleHandler->alter($alter ?: $cid, $data, $context);
        }

        // Only if we have data, cache them.
        if ($data && is_array($data)) {
          if (isset($data[1])) {
            $data = array_unique($data);
          }

          ksort($data);

          $count = count($data);
          $tags = Cache::buildTags($cid, ['count:' . $count]);
          $this->cache->set($cid, $data, Cache::PERMANENT, $tags);
        }

        $this->cachedData[$cid] = $data;
      }
    }
    return $this->cachedData[$cid] ? array_filter($this->cachedData[$cid]) : [];
  }

  /**
   * Alias for BlazyCache::metadata() to forget looking up unknown classes.
   */
  public function getCacheMetadata(array $build = []) {
    return BlazyCache::metadata($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getIoSettings(array $attach = []): object {
    $io = [];
    $thold = $this->configLoad('io.threshold');
    $thold = str_replace(['[', ']'], '', trim($thold ?: '0'));

    // @todo re-check, looks like the default 0 is broken sometimes.
    if ($thold == '0') {
      $thold = '0, 0.25, 0.5, 0.75, 1';
    }

    $thold = strpos($thold, ',') !== FALSE
      ? array_map('trim', explode(',', $thold)) : [$thold];
    $formatted = [];
    foreach ($thold as $value) {
      $formatted[] = strpos($value, '.') !== FALSE ? (float) $value : (int) $value;
    }

    // Respects hook_blazy_attach_alter() for more fine-grained control.
    foreach (['disconnect', 'rootMargin', 'threshold'] as $key) {
      $default = $key == 'rootMargin' ? '0px' : FALSE;
      $value = $key == 'threshold' ? $formatted : $this->configLoad('io.' . $key);
      $io[$key] = $attach['io.' . $key] ?? ($value ?: $default);
    }

    return (object) $io;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageEffects(): array {
    $cid = 'blazy_image_effects';
    if ($data = $this->getCachedData($cid)) {
      return $data;
    }

    $effects[] = 'blur';
    return $this->getCachedData($cid, $effects, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrariesPath($name, $base_path = FALSE): ?string {
    return Blazy::getLibrariesPath($name, $base_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getLightboxes(): array {
    $cid = 'blazy_lightboxes';
    if ($data = $this->getCachedData($cid)) {
      return $data;
    }
    $data = BlazyCache::lightboxes($this->root);
    return $this->getCachedData($cid, $data, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getPath($type, $name, $absolute = FALSE): ?string {
    return Blazy::getPath($type, $name, $absolute);
  }

  /**
   * {@inheritdoc}
   */
  public function getStyles(): array {
    $styles = [
      'column' => 'CSS3 Columns',
      'grid' => 'Grid Foundation',
      'flex' => 'Flexbox Masonry',
      'nativegrid' => 'Native Grid',
    ];
    $this->moduleHandler->alter('blazy_style', $styles);
    return $styles;
  }

  /**
   * Alias for BlazyImage::thumbnail() to forget looking up unknown classes.
   *
   * @todo make it into interface after sub-modules removal.
   */
  public function getThumbnail(array $settings = [], $item = NULL) {
    return BlazyImage::thumbnail($settings, $item);
  }

  /**
   * {@inheritdoc}
   */
  public function isBlazy(array &$settings, array $data = []): void {
    Check::blazyOrNot($settings, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleExists($name): bool {
    return $this->moduleHandler->moduleExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareData(array &$build, $entity = NULL): void {
    // Do nothing, let extenders share data at ease as needed.
  }

  /**
   * {@inheritdoc}
   */
  public function preSettings(array &$settings): void {
    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    $ui = array_intersect_key($this->configLoad(), BlazyDefault::uiSettings());
    $iframe_domain = $this->configLoad('iframe_domain', 'media.settings');
    $is_debug = !$this->configLoad('css.preprocess', 'system.performance');
    $ui['fx'] = $ui['fx'] ?? '';
    $ui['fx'] = empty($settings['fx']) ? $ui['fx'] : $settings['fx'];
    $ui['blur_minwidth'] = (int) ($ui['blur_minwidth'] ?? 0);
    $fx = $settings['_fx'] ?? $ui['fx'];
    $fx = $blazies->get('fx', $fx);
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $lightboxes = $this->getLightboxes();
    $lightboxes = $blazies->get('lightbox.plugins', $lightboxes) ?: [];
    $is_blur = $fx == 'blur';
    $is_resimage = $this->moduleExists('responsive_image');

    $blazies->set('fx', $fx)
      ->set('iframe_domain', $iframe_domain)
      ->set('is.blur', $is_blur)
      ->set('is.debug', $is_debug)
      ->set('is.resimage', $is_resimage)
      ->set('is.unblazy', $this->configLoad('io.unblazy'))
      ->set('language.current', $language)
      ->set('libs.animate', $fx)
      ->set('libs.blur', $is_blur)
      ->set('lightbox.plugins', $lightboxes)
      ->set('ui', $ui);

    if ($router = Path::routeMatch()) {
      $settings['route_name'] = $route_name = $router->getRouteName();
      $blazies->set('route_name', $route_name);
    }

    // Sub-modules may need to provide their data to be consumed here.
    // Basicaly needs basic UI and definitions above to supply data properly,
    // such as to determine Slick/ Splide own lazy load methods based on UI.
    $this->preSettingsData($settings);

    // Preliminary globals when using the provided API.
    Blazy::preSettings($settings);
  }

  /**
   * {@inheritdoc}
   */
  public function postSettings(array &$settings): void {
    Blazy::postSettings($settings);

    // Sub-modules may need to override Blazy definitions.
    $this->postSettingsData($settings);
  }

  /**
   * {@inheritdoc}
   */
  public function toGrid(array $items, array $settings): array {
    return Blazy::grid($items, $settings);
  }

  /**
   * Overrides data massaged by [blazy|slick|splide, etc.]_settings_alter().
   */
  public function postSettingsAlter(array &$settings, $entity = NULL): void {
    Check::settingsAlter($settings, $entity);
  }

  /**
   * Provides data to be consumed by Blazy::preSettings().
   *
   * Such as to provide lazy attribute and class for Slick or Splide, etc.
   */
  protected function preSettingsData(array &$settings): void {
    // Do nothing, let extenders input data at ease as needed.
  }

  /**
   * Overrides data massaged by Blazy::postSettings().
   */
  protected function postSettingsData(array &$settings): void {
    // Do nothing, let extenders override data at ease as needed.
  }

  /**
   * Provides attachments and cache common for all blazy-related modules.
   */
  protected function setAttachments(
    array &$element,
    array $settings,
    array $attachments = []
  ): void {
    $cache                = $this->getCacheMetadata($settings);
    $attached             = $this->attach($settings);
    $attachments          = Blazy::merge($attached, $attachments);
    $element['#attached'] = Blazy::merge($attachments, $element, '#attached');
    $element['#cache']    = Blazy::merge($cache, $element, '#cache');
  }

  /**
   * Builds an entity query.
   */
  private function buildPropertyQuery($query, array $values, $condition = 'IN'): void {
    foreach ($values as $name => $value) {
      // Cast scalars to array so we can consistently use an IN condition.
      $query->condition($name, (array) $value, $condition);
    }
  }

  /**
   * Collects defined skins as registered via hook_MODULE_NAME_skins_info().
   *
   * @todo remove for sub-modules own skins as plugins at blazy:8.x-2.1+.
   * @see https://www.drupal.org/node/2233261
   * @see https://www.drupal.org/node/3105670
   */
  public function buildSkins($namespace, $skin_class, $methods = []) {
    return [];
  }

  /**
   * Deprecated method, not safe to remove before 3.x for being generic.
   *
   * @deprecated in blazy:8.x-2.5 and is removed from blazy:3.0.0. Use
   *   BlazyResponsiveImage::styles() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getResponsiveImageStyles($responsive) {
    return BlazyResponsiveImage::styles($responsive);
  }

  /**
   * Deprecated method, safe to remove before 3.x for being too specific.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::postSettings() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getCommonSettings(array &$settings = []) {
    $this->postSettings($settings);
  }

  /**
   * Deprecated method, safe to remove before 3.x for being too specific.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyEntity::settings() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntitySettings(array &$settings, $entity) {
    BlazyEntity::settings($settings, $entity);
  }

}
