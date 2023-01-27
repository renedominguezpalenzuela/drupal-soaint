<?php

namespace Drupal\migration_tools;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\migrate\MigrateException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\migration_tools\Event\MessageEvent;

/**
 * Class Message.
 *
 * Helper class to manage watchdog and commandline messaging of migrations.
 *
 * @package Drupal\migration_tools
 */
class Message {
  // Provide RFC equiv constants so method calls on Message don't require RFC.
  const EMERGENCY = RfcLogLevel::EMERGENCY;
  const ALERT = RfcLogLevel::ALERT;
  const CRITICAL = RfcLogLevel::CRITICAL;
  const ERROR = RfcLogLevel::ERROR;
  const WARNING = RfcLogLevel::WARNING;
  const NOTICE = RfcLogLevel::NOTICE;
  const INFO = RfcLogLevel::INFO;
  const DEBUG = RfcLogLevel::DEBUG;

  /**
   * Logs a system message and outputs it to drush terminal if run from drush.
   *
   * @param string $message
   *   The message to store in the log. Keep $message translatable
   *   by not concatenating dynamic values into it! Variables in the
   *   message should be added by using placeholder strings alongside
   *   the variables argument to declare the value of the placeholders.
   *   See t() for documentation on how $message and $variables interact.
   * @param array $variables
   *   Array of variables to replace in the message on display or
   *   NULL if message is already translated or not possible to
   *   translate.
   * @param int $severity
   *   The severity of the message; one of the following values as defined in
   *   - Message::EMERGENCY: Emergency, system is unusable.
   *   - Message::ALERT: Alert, action must be taken immediately.
   *   - Message::CRITICAL: Critical conditions.
   *   - Message::ERROR: Error conditions.
   *   - Message::WARNING: Warning conditions.
   *   - Message::NOTICE: (default) Normal but significant conditions.
   *   - Message::INFO: Informational messages.
   *   - Message::DEBUG: Debug-level messages.
   *   - FALSE: Outputs the message to drush without calling Watchdog.
   * @param int $indent
   *   (optional). Sets indentation for drush output. Defaults to 1.
   *
   * @link http://www.faqs.org/rfcs/rfc3164.html RFC 3164: @endlink
   *
   * @throws MigrateException
   */
  public static function make($message, array $variables = [], $severity = self::NOTICE, $indent = 1) {
    // Determine what instantiated this message.
    $trace = debug_backtrace();
    $type = 'unknown';
    self::determineType($type, $trace);
    $parsed_message = new FormattableMarkup($message, $variables);
    $debug_level = \Drupal::config('migration_tools.settings')->get('debug_level');

    if ($severity !== FALSE) {
      $type = (!empty($type)) ? $type : 'migration_tools';

      $event = new MessageEvent($message, $variables, $severity, $type, $parsed_message);
      $event_dispatcher = \Drupal::service('event_dispatcher');
      $event_dispatcher->dispatch(MessageEvent::EVENT_NAME, $event);

      $log_levels = RfcLogLevel::getLevels();
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $log_function_markup */
      $log_function_markup = $log_levels[$severity];

      // Use lowercase version of label for method call.
      $log_function = strtolower($log_function_markup->__toString());
      if (\Drupal::config('migration_tools.settings')->get('debug')) {
        if ($severity <= $debug_level) {
          \Drupal::logger($type)->$log_function($parsed_message);
        }
      }
    }
    // Check to see if this is run by drush and output is desired.
    if (PHP_SAPI === 'cli' && \Drupal::config('migration_tools.settings')->get('drush_debug')) {
      $type = (!empty($type)) ? "{$type}: " : '';
      // Drush does not print all Watchdog messages to terminal only
      // WATCHDOG_ERROR and worse.
      if ($severity <= $debug_level || $severity === FALSE) {
        // Watchdog didn't output it, so output it directly.
        if (function_exists('drush_print')) {
          drush_print($type . $parsed_message, $indent);
        }
			}
      if ((\Drupal::config('migration_tools.settings')->get('drush_stop_on_error')) && ($severity <= self::ERROR) && $severity !== FALSE) {
        throw new MigrateException("{$type}Stopped for debug.\n -- Run \"drush mi {migration being run}\" to try again. \n -- Run \"drush config-set migration_tools.settings drush_stop_on_error 0\" to disable auto-stop.");
      }
    }
  }

  /**
   * Outputs a visual separator using the message system.
   */
  public static function makeSeparator() {
    self::make("------------------------------------------------------", [], FALSE, 0);
  }

  /**
   * Message specific to skipping a migration row.
   *
   * @param string $reason
   *   A short explanation of why it is being skipped.
   * @param string $row_id
   *   The id of the row being skipped.
   * @param int $watchdog_level
   *   The watchdog level to declare.
   *
   * @return bool
   *   FALSE.
   */
  public static function makeSkip($reason, $row_id, $watchdog_level = self::INFO) {
    // Reason is included directly in the message because it needs translation.
    $message = "SKIPPED->{$reason}: @row_id";
    $variables = [
      '@row_id' => $row_id,
    ];
    self::make($message, $variables, $watchdog_level, 1);

    return FALSE;
  }

  /**
   * Generate the import summary.
   *
   * @param array $completed
   *   Array of completed imports.
   * @param int $total_requested
   *   The number to be processed.
   * @param string $operation
   *   The name of the operation being sumaraized.
   *   Ex: Rewrite image src.
   */
  public static function makeSummary(array $completed, $total_requested, $operation) {
    $count = count($completed);
    $long = \Drupal::config('migration_tools.settings')->get('drush_debug');
    if ((int) $long >= 2) {
      // Long output requested.
      $completed_string = self::improveArrayOutput($completed);
      $vars = [
        '@count' => $count,
        '!completed' => $completed_string,
        '@total' => $total_requested,
        '@operation' => $operation,
      ];
      $message = "Summary: @operation @count/@total.  Completed:\n !completed";
    }
    else {
      // Default short output.
      $vars = [
        '@count' => $count,
        '@total' => $total_requested,
        '@operation' => $operation,
      ];
      $message = "Summary: @operation @count/@total.";
    }

    self::make($message, $vars, FALSE, 2);
  }

  /**
   * Stringify and clean up the output of an array for messaging.
   *
   * @param array $array_items
   *   The array to be stringified and cleaned.
   *
   * @return string
   *   The stringified array.
   */
  public static function improveArrayOutput(array $array_items) {
    // Remove any objects from the debug output.
    foreach ($array_items as $key => &$array_item) {
      if (is_array($array_item)) {
        foreach ($array_item as $sub_key => $sub_array_item) {
          if (is_object($sub_array_item)) {
            unset($array_item[$sub_key]);
          }
        }
      }
    }
    $string = print_r($array_items, TRUE);
    $remove = ["Array", "(\n", ")\n"];
    $string = str_replace($remove, '', $string);
    // Adjust for misaligned second line.
    $string = str_replace('             [', '     [', $string);

    return $string;
  }

  /**
   * Determine the type of thing that created the message.
   *
   * @param string $type
   *   The name of the thing that made the message. (by reference)
   * @param array $trace
   *   The stack trace as returned by debug_backtrace.
   */
  private static function determineType(&$type, array $trace) {
    if (isset($trace[1])) {
      // $trace[1] is the thing that instantiated this message.
      if (!empty($trace[1]['class'])) {
        $type = $trace[1]['class'];
      }
      elseif (!empty($trace[1]['function'])) {
        $type = $trace[1]['function'];
      }
    }

    self::reduceTypeNoise($type);
  }

  /**
   * Reduce misleading type, and the noise of full namespace output.
   *
   * @param string $type
   *   The type that needs to be de-noised or reduced in length.
   */
  private static function reduceTypeNoise(&$type) {
    // A list of types to blank out, to reduce deceptive noise.
    $noise_filter = [
      'Drupal\migration_tools\Message',
    ];
    $type = ((in_array($type, $noise_filter))) ? '' : $type;

    // A list of types to increase readability and reduce noise.
    $noise_shorten = [
      'Drupal\migration_tools\Obtainer' => 'MT',
      'Drupal\migration_tools' => 'MT',
    ];
    $type = str_replace(array_keys($noise_shorten), array_values($noise_shorten), $type);
  }

  /**
   * Dumps a var to drush terminal if run by drush.
   *
   * @param mixed $var
   *   Any variable value to output.
   * @param string $var_id
   *   An optional string to identify what is being output.
   */
  public static function varDumpToDrush($var, $var_id = 'VAR DUMPs') {
    // Check to see if this is run by drush and output is desired.
    if (PHP_SAPI === 'cli' && \Drupal::config('migration_tools.settings')->get('drush_debug')) {
      if (function_exists('drush_print')) {
        drush_print("{$var_id}: \n", 0);
      }
      if (!empty($var)) {
        if (function_exists('drush_print_r')) {
          drush_print_r($var);
        }
      }
      else {
        if (function_exists('drush_print')) {
          drush_print("This variable was EMPTY. \n", 1);
        }
      }
    }
  }

}
