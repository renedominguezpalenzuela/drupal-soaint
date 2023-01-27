<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Field\BlazyEntityVanillaBase;

/**
 * Base class for entity reference formatters without field details.
 *
 * Used by sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Field\BlazyEntityVanillaBase instead.
 * @see https://www.drupal.org/node/3103018
 */
abstract class BlazyEntityBase extends BlazyEntityVanillaBase {}
