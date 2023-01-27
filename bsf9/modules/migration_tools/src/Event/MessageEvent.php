<?php

namespace Drupal\migration_tools\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\Component\Render\FormattableMarkup;

class MessageEvent extends Event {

  /**
   * The template string for the message.
   *
   * @var string
   */
  public $messageTemplate;

  /**
   * The message variables.
   *
   * @var array
   */
  public $variables;

  /**
   * The message.
   *
   * @var string
   */
  public $message;

  /**
   * The RfcLogLevel of the message
   *
   * @var int
   */
  public $severity;

  /**
   * The type of message (usually the name of the class that made it).
   *
   * @var string
   */
  public $type;

  const EVENT_NAME = 'migration_tools_message';

  /**
   * MessageEvent constructor.
   *
   * @param string $message_template
   *   The string used to format the message.
   * @param array $variables
   *   Any variables passage to Message::make()
   * @param int $severity
   *   The severity level.
   * @param string $type
   * @param string $message
   */
  public function __construct($message_template, $variables, $severity, $type, $message) {
    $this->messageTemplate = $message_template;
    $this->variables = $variables;
    $this->severity = $severity;
    $this->type = $type;
    $this->message = $message;
  }

}
