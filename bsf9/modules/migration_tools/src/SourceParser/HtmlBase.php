<?php

namespace Drupal\migration_tools\SourceParser;

use Drupal\migrate\MigrateException;
use Drupal\migration_tools\Message;
use Drupal\migration_tools\Obtainer\Job;
use Drupal\migrate\Row;

/**
 * Class SourceParser\Base.
 *
 * Defines SourceParser\Base class, that parses static HTML files via queryPath.
 *
 * @package migration_tools
 */
class HtmlBase {

  public $obtainerJobs = [];
  public $fileId;
  protected $html;

  /** @var \Drupal\migrate\Row $row */
  public $row;
  public $queryPath;

  /**
   * A source parser class should set a useful set of obtainer jobs.
   *
   * These should be the basics for a given content type.  More searches can be
   * added to the source parser before parse() is called.
   */
  protected function setDefaultObtainerJobs() {
    // @todo New architecture may not need default jobs.
  }

  /**
   * A source parser class should perform any checks on required properties.
   *
   * Appropriate messages should be output.
   * This will be called at the end of parse().
   */
  protected function validateParse() {
    // @todo May not be necessary when validators are moved to new class.
  }

  /**
   * Constructor.
   *
   * @param string $file_id
   *   The file id, e.g. careers/legal/pm7205.html.
   * @param string $html
   *   The full HTML data as loaded from the file.
   * @param \Drupal\migrate\Row $row
   *   Migrate row to be altered.
   */
  public function __construct($file_id, $html, Row $row) {
    $this->fileId = $file_id;
    $this->row = $row;
    $this->html = $html;

    $this->initQueryPath();
    $this->setDefaultObtainerJobs();
  }

  /**
   * Add obtainer job for this source parser to run.
   *
   * @param \Drupal\migration_tools\Obtainer\Job $job
   *   Job to add.
   */
  public function addObtainerJob(Job $job) {
    $this->obtainerJobs[$job->getProperty()] = $job;
  }

  /**
   * Get Obtainer Jobs.
   *
   * @return array
   *   Array of jobs.
   */
  public function getObtainerJobs() {
    return $this->obtainerJobs;
  }

  /**
   * Parse the page by running all the ObtainerJobs.
   *
   * This should be called after all obtainer jobs have been added.
   */
  public function parse() {
    // Run all Obtainer jobs.
    if (isset($this->obtainerJobs) && is_array($this->obtainerJobs)) {
      foreach ($this->obtainerJobs as $job) {
        $property = $job->getProperty();
        $this->row->setSourceProperty($property, $this->getProperty($property));
      }
      $this->validateParse();
    }
  }

  /**
   * Get information/properties from html by running the obtainers.
   *
   * @param string $property
   *   Property to get.
   */
  protected function getProperty($property) {
    if (!isset($this->{$property})) {
      $this->setProperty($property);
    }

    // We can just return the property as any issue should throw an exception
    // form setProperty.
    return $this->{$property};
  }

  /**
   * Set a property.
   *
   * @param string $property
   *   Property to set.
   */
  protected function setProperty($property) {
    // Make sure our QueryPath object has been initialized.
    $this->initQueryPath();
    // Obtain the property using obtainers.
    $this->{$property} = $this->obtainProperty($property);
    $this->updateJobsArguments();
  }

  /**
   * Use the obtainers mechanism to extract text from the html.
   *
   * @param string $property
   *   Property to get.
   *
   * @return string
   *   Property text.
   *
   * @throws \Exception
   */
  protected function obtainProperty($property) {
    $text = '';
    $job = (empty($this->obtainerJobs[$property])) ? '' : $this->obtainerJobs[$property];

    if (empty($job)) {
      $message = t("@class does not have a Job defined for the  property: @property", ['@property' => $property, '@class' => 'SourceParser\HtmlBase']);
      throw new \Exception($message);
    }

    try {
      $class = $job->getClassShortName();
      $searches = $job->getSearches();
      if (!empty($searches)) {
        // There are methods to run, so run them.
        Message::make("Obtaining @key via @obtainer_class", ['@key' => $property, '@obtainer_class' => $class], Message::DEBUG);

        $text = $job->run($this->queryPath);
        $length = (is_array($text)) ? count($text) : strlen($text);

        if (!$length) {
          // Nothing was obtained.
          Message::make('@property NOT found', ['@property' => $property], Message::DEBUG, 2);
        }
        elseif (is_array($text)) {
          // This must have come from Obtain[].
          Message::make('@property found --> @array', ['@property' => $property, '@array' => Message::improveArrayOutput($text)], Message::DEBUG, 2);
        }
        elseif ($length < 256) {
          // It is short enough to be helpful in debug output.
          Message::make('@property found --> @text', ['@property' => $property, '@text' => $text], Message::DEBUG, 2);
        }
        else {
          // It's too long to be helpful in output so just show the length.
          Message::make('@property found --> Length: @length', ['@property' => $property, '@length' => $length], Message::DEBUG, 2);
        }
      }
      else {
        // There were no methods to run so message.
        Message::make("There were no searches to run for @key via @obtainer_class so it was not executed", ['@key' => $property, '@obtainer_class' => $class]);
      }

    }
    catch (\Exception $e) {
      Message::make("@file_id Failed to set @key, Exception: @error_message", [
        '@file_id' => $this->fileId,
        '@key' => $property,
        '@error_message' => $e->getMessage(),
      ], Message::DEBUG);
    }

    return $text;
  }

  /**
   * Create the queryPath object.
   */
  protected function initQueryPath() {
    if (isset($this->queryPath)) {
      // QueryPath is already initialized.
      return;
    }
    else {
      // Initialize the QueryPath.
      $type_detect = [
        'UTF-8',
        'ASCII',
        'ISO-8859-1',
        'ISO-8859-2',
        'ISO-8859-3',
        'ISO-8859-4',
        'ISO-8859-5',
        'ISO-8859-6',
        'ISO-8859-7',
        'ISO-8859-8',
        'ISO-8859-9',
        'ISO-8859-10',
        'ISO-8859-13',
        'ISO-8859-14',
        'ISO-8859-15',
        'ISO-8859-16',
        'Windows-1251',
        'Windows-1252',
        'Windows-1254',
      ];
      $convert_from = mb_detect_encoding($this->html, $type_detect);
      if ($convert_from != 'UTF-8') {
        // This was not UTF-8 so report the anomaly.
        $message = "Converted from: @convert_from";
        Message::make($message, ['@convert_from' => $convert_from], Message::INFO, 1);
      }

      $qp_options = [
        'convert_to_encoding' => 'UTF-8',
        'convert_from_encoding' => $convert_from,
      ];

      // Create query path object.
      try {
        // QueryPath is need as part of this migration but is not a full
        // dependency for this module.  It can be included as the Drupal
        // querypath module or as a library.
        if (function_exists('qp')) {
          try {
            // The QueryPath qp is less destructive than htmlqp so try it first.
            $this->queryPath = qp($this->html, NULL, $qp_options);
          }
          catch (\Exception $e) {
            // QueryPath qp is less tolerant of badly formed html so it must
            // have failed.
            // Use htmlqp which is more detructive but will fix bad html.
            Message::make('Failed to instantiate QueryPath using qp, attempting qphtml with @file, Exception: @error_message', ['@error_message' => $e->getMessage(), '@file' => $this->fileId], Message::WARNING);
            $this->queryPath = htmlqp($this->html, NULL, $qp_options);
          }
        }
        else {
          $message = "QueryPath is required for html source parsing.  Please install the querypath module or add the library.";
          throw new MigrateException($message);
        }
      }
      catch (\Exception $e) {
        Message::make('Failed to instantiate QueryPath for HTML, Exception: @error_message', ['@error_message' => $e->getMessage()], Message::ERROR);
      }
      // Sometimes queryPath fails.  So one last check.
      if (!is_object($this->queryPath)) {
        throw new MigrateException("{$this->fileId} failed to initialize QueryPath");
      }
    }
  }

  /**
   * Update Jobs Argument dynamic variables.
   */
  protected function updateJobsArguments() {
    $dynamic_args = get_object_vars($this);
    foreach ($this->obtainerJobs as $job_key => &$job) {
      foreach ($job->searches as &$search) {
        self::parseDynamicArguments($search['arguments'], $dynamic_args);
      }
    }
  }

  /**
   * Parse Dynamic Arguments.
   *
   * @param array $arguments
   *   Arguments to parse for dynamic arguments (start with '@').
   * @param array $dynamic_args
   *   Array of dynamic args to replace from.
   */
  public static function parseDynamicArguments(&$arguments, $dynamic_args) {
    foreach ($arguments as &$argument) {
      if (!is_array($argument)) {
        $matches = [];
        if (preg_match('/@(\w*)/', $argument, $matches)) {
          $dynamic_arg_name = $matches[1];
          if (isset($dynamic_args[$dynamic_arg_name])) {
            $argument = preg_replace('/@\w*/', $dynamic_args[$dynamic_arg_name], $argument);
          }
        }
      }
    }
  }

}
