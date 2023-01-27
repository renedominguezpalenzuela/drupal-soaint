<?php

namespace Drupal\blazy\Dejavu;

use Drupal\blazy\Field\BlazyEntityReferenceBase as EntityReferenceBase;

/**
 * Base class for all entity reference formatters with field details.
 *
 * Used by sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:8.x-3.0. Use
 *   Drupal\blazy\Field\BlazyEntityReferenceBase instead.
 * @see https://www.drupal.org/node/3103018
 */
abstract class BlazyEntityReferenceBase extends EntityReferenceBase {}
