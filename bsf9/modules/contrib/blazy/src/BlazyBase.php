<?php

namespace Drupal\blazy;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\blazy\Cache\BlazyCache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common non-media related methods across Blazy ecosystem to DRY.
 *
 * @todo remove this line, non-functional till extended by BlazyManagerBase.
 */
abstract class BlazyBase implements BlazyInterface {

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
   * Constructs a BlazyBase object.
   */
  public function __construct($root, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, LanguageManager $language_manager) {
    $this->root              = $root;
    $this->entityRepository  = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
    $this->languageManager   = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      Blazy::root($container),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('language_manager')
    );
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
   * Returns the entity repository service.
   */
  public function entityRepository() {
    return $this->entityRepository;
  }

  /**
   * Returns the entity type manager service.
   */
  public function entityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Returns the module handler service.
   */
  public function moduleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer service.
   */
  public function renderer() {
    return $this->renderer;
  }

  /**
   * Returns the config factory service.
   */
  public function configFactory() {
    return $this->configFactory;
  }

  /**
   * Returns the cache service.
   */
  public function cache() {
    return $this->cache;
  }

  /**
   * Returns any config, or keyed by the $setting_name.
   */
  public function config($setting_name = '', $settings = 'blazy.settings') {
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
   * Returns a shortcut for loading an entity: image_style, slick, etc.
   */
  public function load($id, $type = 'image_style') {
    return $this->getStorage($type)->load($id);
  }

  /**
   * Returns a shortcut for loading multiple entities.
   */
  public function loadMultiple($type = 'image_style', $ids = NULL) {
    return $this->getStorage($type)->loadMultiple($ids);
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
   * {@inheritdoc}
   */
  public function getCacheMetadata(array $build = []) {
    return BlazyCache::metadata($build);
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
  public function getPath($type, $name, $absolute = FALSE): ?string {
    return Blazy::getPath($type, $name, $absolute);
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
  public function toGrid(array $items, array $settings): array {
    return Blazy::grid($items, $settings);
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

}
