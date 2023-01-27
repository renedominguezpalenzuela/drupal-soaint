<?php

namespace Drupal\blazy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\Media\BlazyOEmbedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides common entity utilities to work with field details.
 */
class BlazyEntity implements BlazyEntityInterface {

  /**
   * The blazy oembed service.
   *
   * @var object
   */
  protected $oembed;

  /**
   * The blazy manager service.
   *
   * @var object
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyFormatter instance.
   */
  public function __construct(BlazyOEmbedInterface $oembed) {
    $this->oembed = $oembed;
    $this->blazyManager = $oembed->blazyManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('blazy.oembed')
    );
  }

  /**
   * Returns the blazy oembed service.
   */
  public function oembed() {
    return $this->oembed;
  }

  /**
   * Returns the blazy manager service.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   *
   * @todo make it single param after sub-modules for easy updates.
   */
  public function build(array &$data, $entity = NULL, $fallback = ''): array {
    $entity = $data['entity'] ?? $entity;
    $fallback = $data['fallback'] ?? $fallback;

    if (!$entity instanceof EntityInterface) {
      return [];
    }

    unset($data['entity'], $data['fallback']);

    // Supports core Media via Drupal\blazy\Media\BlazyOEmbed::build().
    $manager = $this->blazyManager;
    $settings = &$data['settings'];
    $delta = $settings['delta'] ?? -1;

    // Common settings.
    $manager->preSettings($settings);
    $manager->prepareData($data, $entity);
    $manager->postSettings($settings);

    // Entity settings.
    self::settings($settings, $entity);

    $manager->postSettingsAlter($settings, $entity);

    // Build the Media item.
    $this->oembed->build($data, $entity);

    $settings = &$data['settings'];

    // Only pass to Blazy for known entities related to File or Media.
    if (in_array($entity->getEntityTypeId(), ['file', 'media'])) {
      /** @var Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      if (empty($data['item'])) {
        $data['content'][] = $this->view($entity, $settings, $fallback);
      }

      // Pass it to Blazy for consistent markups.
      $build = $manager->getBlazy($data, $delta);

      // Allows top level elements to load Blazy once rather than per field.
      // This is still here for non-supported Views style plugins, etc.
      if (empty($settings['_detached'])) {
        $load = $manager->attach($settings);
        $build['#attached'] = Blazy::merge($load, $build, '#attached');
      }
    }
    else {
      $build = $this->view($entity, $settings, $fallback);
    }

    $manager->getModuleHandler()->alter('blazy_build_entity', $build, $entity, $settings);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function view($entity, array $settings = [], $fallback = ''): array {
    if ($fallback && is_string($fallback)) {
      $fallback = ['#markup' => '<div class="is-fallback">' . $fallback . '</div>'];
    }
    $fallback = $fallback ?: [];

    if ($entity instanceof EntityInterface) {
      $manager        = $this->blazyManager;
      $entity_type_id = $entity->getEntityTypeId();
      $view_mode      = $settings['view_mode'] = empty($settings['view_mode'])
        ? 'default' : $settings['view_mode'];
      $langcode       = $entity->language()->getId();

      // If entity has view_builder handler.
      if ($manager->getEntityTypeManager()
        ->hasHandler($entity_type_id, 'view_builder')) {
        $build = $manager->getEntityTypeManager()
          ->getViewBuilder($entity_type_id)
          ->view($entity, $view_mode, $langcode);

        // @todo figure out why video_file empty, this is blatant assumption.
        if ($entity_type_id == 'file') {
          try {
            $build = BlazyField::getOrViewMedia($entity, $settings, TRUE) ?: $build;
          }
          catch (\Exception $ignore) {
            // Do nothing, no need to be chatty in mischievous deeds.
          }
        }
        return $build ?: $fallback;
      }
      else {
        // If module implements own {entity_type}_view.
        // @todo remove due to being deprecated at D8.7.
        // See https://www.drupal.org/node/3033656
        $view_hook = $entity_type_id . '_view';
        if (is_callable($view_hook)) {
          return $view_hook($entity, $view_mode, $langcode);
        }
      }
    }
    return $fallback;
  }

  /**
   * Modifies the common settings extracted from the given entity.
   */
  public static function settings(array &$settings, $entity): void {
    // Might be accessed by tests, or anywhere outside the workflow.
    Blazy::verify($settings);

    $blazies = $settings['blazies'];
    $internal_path = $absolute_path = NULL;
    $langcode = $blazies->get('language.current');

    // @todo remove after test updates.
    if (!$entity) {
      return;
    }

    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew()) {
      try {
        // Provides translated $entity, if any.
        $entity = Blazy::translated($entity, $langcode);
        $url = $entity->toUrl();

        $internal_path = $url->getInternalPath();
        $absolute_path = $url->setAbsolute()->toString();
      }
      catch (\Exception $ignore) {
        // Do nothing.
      }
    }

    $id = $entity->id();
    $rid = $entity->getRevisionID();
    $blazies->set('cache.keys', [$id, $rid], TRUE);

    $info = [
      'bundle' => $entity->bundle(),
      'id' => $id,
      'rid' => $rid,
      'type_id' => $entity->getEntityTypeId(),
      'url' => $absolute_path,
      'path' => $internal_path,
    ];

    $blazies->set('entity', $info, TRUE);

    // @todo remove.
    $settings['bundle'] = $entity->bundle();

    // @todo remove after migration and sub-modules. After tests updated.
    // foreach ($info as $key => $value) {
    // $key = $key == 'url' ? 'content_' . $key : $key;
    // $key = in_array($key, ['id', 'type_id']) ? 'entity_' . $key : $key;
    // $settings[$key] = $value;
    // }
  }

  /**
   * {@inheritdoc}
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::view() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    return $this->view($entity, $settings, $fallback);
  }

  /**
   * Returns the formatted renderable array of the field, called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::view() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldRenderable($entity, $field_name, $view_mode, $multiple = TRUE) {
    return BlazyField::view($entity, $field_name, $view_mode, $multiple);
  }

  /**
   * Returns the string value of link, or text, called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getString() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldString($entity, $field_name, $langcode, $clean = TRUE) {
    return BlazyField::getString($entity, $field_name, $langcode, $clean);
  }

  /**
   * Returns the text or link value of the fields: link, or text.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getTextOrLink() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldTextOrLink($entity, $field_name, $settings, $multiple = TRUE) {
    $langcode  = $settings['langcode'] ?? '';
    $view_mode = $settings['view_mode'] ?? 'default';
    return BlazyField::getTextOrLink($entity, $field_name, $view_mode, $langcode, $multiple);
  }

  /**
   * Returns the string value of the fields: link, or text.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getValue() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFieldValue($entity, $field_name, $langcode) {
    return BlazyField::getValue($entity, $field_name, $langcode);
  }

  /**
   * Returns file view or media due to being empty returned by view builder.
   *
   * @deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getOrViewMedia() instead.
   * @see https://www.drupal.org/node/3103018
   */
  public function getFileOrMedia($file, array $settings, $rendered = TRUE) {
    return BlazyField::getOrViewMedia($file, $settings, $rendered);
  }

}
