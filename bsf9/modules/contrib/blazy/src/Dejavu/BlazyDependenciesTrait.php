<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Field\BlazyDependenciesTrait as DependenciesTrait;

/**
 * A Trait common for file, image or media to handle dependencies.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Field\BlazyDependenciesTrait instead.
 * @see https://www.drupal.org/node/3103018
 */
trait BlazyDependenciesTrait {

  use DependenciesTrait;

}
