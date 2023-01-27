<?php

namespace Drupal\views_migration\Plugin;

/**
 * An interface for plugins which alter Handler Associated Plugin settings.
 *
 * There are a number of Views plugins that are directly associated with
 * Handlers. For example:
 *  - Argument Default Plugins on Argument Handlers
 *  - Argument Validator Plugins on Argument Handlers
 *  - Style Summary Plugins on Argument Handlers.
 *
 * These Views Migrate plugins enable the plugins settings to be modified for
 * the Handler they are associated with.
 */
interface MigrateViewsHandlerAssociatedInterface extends MigrateViewsHandlerInterface {

}
