<?php

namespace Drupal\migration_tools\Obtainer;

use QueryPath;
use Drupal\migration_tools\Message;
use Drupal\migration_tools\StringTools;

/**
 * Obtainer Abstract Class.
 *
 * The Obtainer serves to find a target string within DOM markup. It will
 * iterate over a stack of finder methods until it finds the string that it is
 * looking for, at which point it returns the string.
 */
abstract class Obtainer {

  /**
   * The QueryPath element to be tested.
   *
   * @var object
   */
  private $element;

  /**
   * The find method that is currently being run.
   *
   * @var string
   */
  protected $currentFindMethod;


  /**
   * QueryPath object passed in at instantiation.
   *
   * @var object
   */
  protected $queryPath;

  /**
   * Array of find methods to call, in order. Passed in at instantiation.
   *
   * @var array
   */
  private $methodStack = [];

  /**
   * Constructor for the Obtainer.
   *
   * @param object $query_path
   *   The query path object to use as the source of possible content.
   * @param array $method_stack
   *   (optional). Array of find methods to run through.
   */
  public function __construct($query_path, array $method_stack = []) {
    $this->queryPath = $query_path;
    $this->setMethodStack($method_stack);
  }

  /**
   * Sets the $methodStack property, checking that each method is valid.
   *
   * @param array $method_stack
   *   The stack of methods to be run.
   */
  public function setMethodStack(array $method_stack) {
    foreach ($method_stack as $key => $method) {
      if (!method_exists($this, $method['method_name'])) {
        unset($method_stack[$key]);
        Message::make('The target method @method does not exist and was skipped.', ['@method' => $method['method_name']], Message::DEBUG);
      }
    }
    $this->methodStack = $method_stack;
  }

  /**
   * Sets the $currentFindMethod property.
   *
   * @param string $current_find_method
   *   The name of the current find method.
   */
  public function setCurrentFindMethod($current_find_method) {
    if (!empty($current_find_method)) {
      $this->currentFindMethod = $current_find_method;
    }
  }

  /**
   * Getter for $currentFindMethod.
   *
   * @return string
   *   The current value of $currentFindMethod.
   */
  public function getCurrentFindMethod() {
    return $this->currentFindMethod;
  }

  /**
   * Sets $this->element.
   *
   * @param object $element
   *   The QueryPath element to be removed from source if string is obtained.
   */
  public function setElementToRemove($element) {
    $this->element = $element;
  }

  /**
   * Calls remove() on the currently set QueryPath element.
   */
  public function removeElement() {
    if (!empty($this->element)) {
      $this->element->remove();
    }
  }

  /**
   * Processes each method in method stack until a string is found.
   */
  public function obtain() {
    // Loop through the stack.
    foreach ($this->methodStack as $key => $method) {
      // Run the method. It is expected that the method will return a string.
      $this->setCurrentFindMethod($method['method_name']);
      // Reset QueryPath pointer to top of document.
      $this->queryPath->top();
      $found_string = call_user_func_array([$this, $method['method_name']], $method['arguments']);
      $found_string = $this->cleanString($found_string);
      if ($this->validateString($found_string)) {
        // Give child classes opportunity to process the string before return.
        $found_string = $this->processString($found_string);
        // @TODO This is needed to give necessary updated info on what finder
        // configuration actually worked so the developer can tune the methods
        // stack efficiently. Replace this with an iterator when it is ready.
        $method['method_name'] = $this->getCurrentFindMethod();
        $type = (is_array($found_string)) ? 'array' : 'string';

        Message::make('@method found a @type.', ['@method' => $method['method_name'], '@type' => $type], Message::DEBUG, 2);

        // Remove the element from the DOM and exit loop.
        $this->removeElement();

        return $found_string;
      }
    }

    Message::make('NO MATCHES FOUND', [], Message::DEBUG, 2);
    return '';
  }

  /**
   * Cleans $text and returns it prior to validation.
   *
   * @param string $string
   *   Text to clean and return.
   *
   * @return string
   *   The cleaned text.
   */
  public static function cleanString($string) {
    // There are also numeric html special chars, let's change those.
    $string = StringTools::decodehtmlentitynumeric($string);
    // Checking again in case another process rendered it non UTF-8.
    $is_utf8 = mb_check_encoding($string, 'UTF-8');

    if (!$is_utf8) {
      $string = StringTools::fixEncoding($string);
    }

    $string = StringTools::stripCmsLegacyMarkup($string);
    // Remove white space-like things from the ends and decodes html entities.
    $string = StringTools::superTrim($string);

    return $string;
  }

  /**
   * Evaluates $possibleText and if it checks out, returns TRUE.
   *
   * @param string $string
   *   The string to validate.
   *
   * @return bool
   *   TRUE if possibleText can be used as a title. FALSE if it can't.
   */
  protected function validateString($string) {
    // Run through any evaluations. If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case empty($string):
      case is_object($string):
      case is_array($string):
        return FALSE;

      default:
        return TRUE;
    }
  }

  /**
   * Processes a valid string before returning.
   *
   * @param string $string
   *   The found, valid string.
   *
   * @return string
   *   The string to be returned.
   */
  protected function processString($string) {
    return $string;
  }

}
