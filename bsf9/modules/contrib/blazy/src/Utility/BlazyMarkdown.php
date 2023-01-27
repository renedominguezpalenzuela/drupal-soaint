<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\Xss;
use Michelf\MarkdownExtra;
use League\CommonMark\CommonMarkConverter;

/**
 * Provides markdown utilities only useful for the help text.
 */
class BlazyMarkdown {

  /**
   * Checks if we have the needed classes.
   */
  public static function isApplicable() {
    return class_exists('Michelf\MarkdownExtra') || class_exists('League\CommonMark\CommonMarkConverter');
  }

  /**
   * Processes Markdown text, and convert into HTML suitable for the help text.
   *
   * @param string $text
   *   The text to apply the Markdown filter to.
   * @param bool $sanitize
   *   True, if the text should be sanitized.
   * @param bool $help
   *   True, if the text will be used for Help pages.
   *
   * @return string
   *   The filtered, or raw converted text.
   */
  public static function parse($text, $sanitize = TRUE, $help = TRUE) {
    if (!self::isApplicable()) {
      return $help ? '<pre>' . $text . '</pre>' : $text;
    }

    if (class_exists('Michelf\MarkdownExtra')) {
      $text = MarkdownExtra::defaultTransform($text);
    }
    elseif (class_exists('League\CommonMark\CommonMarkConverter')) {
      $converter = new CommonMarkConverter();
      $text = $converter->convertToHtml($text);
    }

    // We do not pass it to FilterProcessResult, as this is meant simple.
    return $sanitize ? Xss::filterAdmin($text) : $text;
  }

}
