<?php

namespace Drupal\migration_tools\Modifier;

use Drupal\migration_tools\Message;

/**
 * Modifier abstract class.
 */
abstract class Modifier {

  /**
   * Calls a modifier method.
   *
   * @param string $method_name
   *   The name of the method to call.
   * @param array $arguments
   *   (optional) An array of arguments to be passed to the $method. Defaults
   *   to an empty array.
   *
   * @return object
   *   Returns $this to allow chaining.
   */
  public function runModifier($method_name, array $arguments = []) {
    if (!method_exists($this, $method_name)) {
      Message::make('The modifier method @method does not exist and was skipped.', ['@method' => $method_name], Message::DEBUG);
    }

    call_user_func_array([$this, $method_name], $arguments);
    return $this;
  }
}
