<?php

namespace Drupal\blazy\Media;

/**
 * Provides OEmbed integration.
 */
interface BlazyOEmbedInterface {

  /**
   * Returns the oEmbed Resource based on the given media input url.
   *
   * @param string $input_url
   *   The video url.
   *
   * @return Drupal\media\OEmbed\Resource[]
   *   The oEmbed resource.
   */
  public function getResource($input_url);

  /**
   * Builds media-related settings based on the given media input url.
   *
   * Accepts various sources: BlazyFilter, BlazyViewsFieldFile, BlazyEntity,
   * regular Media/ OEmbed related formatters, and the deprecated VEF.
   * Old VEF/BVEF might have no expected entity to associate with.
   *
   * @param array $build
   *   The array being modified containing: content, settings and image item.
   *   Or just settings content for old deprecated approach.
   * @param object $entity
   *   The Media entity, File entity, ER, FieldItemList, etc., optional
   *   to accomodate old approach (pre 2.10) and UGC.
   */
  public function build(array &$build, $entity = NULL): void;

}
