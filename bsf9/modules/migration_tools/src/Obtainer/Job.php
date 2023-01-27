<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Information about which property we are dealing with.
 *
 * Including the class and methods to be called within that obtainer.
 */
class Job {
  private $obtainerClassName;
  private $rowProperty;
  public $searches = [];

  /**
   * Constructor.
   *
   * @param string $row_property
   *   The property of the row that will will be created and populated when this
   *   job is run.
   * @param string $obtainer_class_name
   *   The name of the Obtainer class to run.
   */
  public function __construct($row_property, $obtainer_class_name) {
    $this->rowProperty = $row_property;
    $this->setClass($obtainer_class_name);
  }

  /**
   * Setter.
   */
  private function setClass($obtainer_class_name) {
    if (class_exists($obtainer_class_name)) {
      // This passed in with a full correct namespace.
      $this->obtainerClassName = $obtainer_class_name;
    }
    elseif (class_exists("\\Drupal\\migration_tools\\Obtainer\\{$obtainer_class_name}")) {
      // This is in the obtainer namespace.
      $this->obtainerClassName = "\\Drupal\\migration_tools\\Obtainer\\{$obtainer_class_name}";
    }
    elseif (class_exists("\\{$obtainer_class_name}")) {
      // This was in its own namespace.
      $this->obtainerClassName = "\\{$obtainer_class_name}";
    }
    else {
      // The class does not exist.
      $message = t("The class @class does not exist.", ['@class' => $obtainer_class_name]);
      throw new \Exception($message);
    }
  }

  /**
   * Getter.
   */
  public function getProperty() {
    return $this->rowProperty;
  }

  /**
   * Getter.
   */
  public function getClass() {
    return $this->obtainerClassName;
  }

  /**
   * Shortens the name of the Obtainer class for output.
   *
   * @return string
   *   The name of the obtainer class being run, without the namespacing.
   */
  public function getClassShortName() {
    $name = $this->getClass();
    $name = str_replace('\Drupal\migration_tools\Obtainer\\', '', $name);

    return $name;
  }

  /**
   * Add a new method to be called during obtainer processing.
   *
   * @param string $method_name
   *   The name of the method to call.
   * @param array $arguments
   *   (optional) An array of arguments to be passed to the $method. Defaults
   *   to an empty array.
   *
   * @return Job
   *   Returns $this to allow chaining.
   */
  public function addSearch($method_name, array $arguments = []) {
    // @todo Maybe we should validate the method names here?
    $this->searches[] = [
      'method_name' => $method_name,
      'arguments' => $arguments,
    ];

    return $this;
  }

  /**
   * Getter.
   */
  public function getSearches() {
    return $this->searches;
  }

  /**
   * Runs the obtainer job.
   *
   * @param object $query_path
   *   The query path object by reference.
   *
   * @return string
   *   Obtain string.
   */
  public function run(&$query_path) {
    $obtainer_class = $this->getClass();
    $obtainer = new $obtainer_class($query_path, $this->getSearches());

    return $obtainer->obtain();
  }

}
