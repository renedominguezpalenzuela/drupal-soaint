<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\Message;
use Drupal\migration_tools\StringTools;

/**
 * Class ObtainTitle
 *
 * Contains a collection of stackable finders that can be arranged
 * as needed to obtain a title/heading and possible subtitle/subheading.
 */
class ObtainTitle extends ObtainHtml {

  /**
   * {@inheritdoc}
   */
  protected function processString($string) {
    return $this->truncateString($string);
  }

  /**
   * Truncates and sets the discarded if there is a remainder.
   */
  public static function truncateString($string) {
    $split = self::truncateThisWithoutHTML($string, 255, 2);

    // If something got trimmed off, message it.
    if (!empty($split['remaining'])) {
      $message = "The title was shortened and lost: @remainder";
      Message::make($message, ['@remainder' => $split['remaining']], Message::ERROR, 2);
    }

    return $split['truncated'];
  }

  /**
   * Finder method to find the content sub-banner alt.
   *
   * @return string
   *   The text found.
   */
  protected function findSubBannerAlt() {
    return $this->findSubBannerAttr('alt');
  }

  /**
   * Finder method to find the content sub-banner title.
   *
   * @return string
   *   The text found.
   */
  protected function findSubBannerTitle() {
    return $this->findSubBannerAttr('title');
  }

  /**
   * Grab method to find the content sub-banner attribute.
   *
   * @return string
   *   The text found.
   */
  protected function findSubBannerAttr($attribute = 'alt') {
    $title = $this->findSubBannerString($attribute);
    // Remove the text 'banner'.
    $title = str_ireplace('banner', '', $title);
    // Check to see if alt is just placeholder to discard.
    $placeholder_texts = [
      'placeholder',
      'place-holder',
      'place_holder',
    ];
    foreach ($placeholder_texts as $needle) {
      if (stristr($title, $needle)) {
        // Just placeholder text, so ignore this text.
        $title = '';
      }
    }

    return $title;
  }

  /**
   * Get subbanner image.
   */
  protected function findSubBannerString($attribute = 'alt') {
    $images = $this->queryPath->find('img');
    foreach ($images as $image) {
      $src = $image->attr('src');
      if (stristr($src, 'subbanner')) {
        return $image->attr($attribute);
      }
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public static function cleanString($text) {
    // Breaks need to be converted to spaces to avoid lines running together.
    // @codingStandardsIgnoreStart
    $break_tags = ['<br>', '<br/>', '<br />', '</br>'];
    // @codingStandardsIgnoreEnd
    $text = str_ireplace($break_tags, ' ', $text);
    $text = strip_tags($text);
    // Titles can not have html entities.
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // There are also numeric html special chars, let's change those.
    $text = StringTools::decodehtmlentitynumeric($text);

    // We want out titles to be only digits and ascii chars so we can produce
    // clean aliases.
    $text = StringTools::convertNonASCIItoASCII($text);
    // Remove undesirable chars and strings.
    $remove = [
      '&raquo;',
      '&nbsp;',
      '»',
      // Weird space character.'.
      ' ',
    ];
    $text = str_ireplace($remove, ' ', $text);

    // Remove white space-like things from the ends and decodes html entities.
    $text = StringTools::superTrim($text);
    // Remove multiple spaces.
    $text = preg_replace(['/\s{2,}/', '/[\t\n]/'], ' ', $text);

    // Convert to ucwords If the entire thing is caps. Otherwise leave it alone
    // for preservation of acronyms.
    // Caveat: will obliterate acronyms if the entire title is caps.
    $uppercase_version = strtoupper($text);
    similar_text($uppercase_version, $text, $percent);
    if ($percent > 95.5) {
      // Nearly the entire thing is caps.
      $text = strtolower($text);
    }
    $text = StringTools::makeWordsFirstCapital($text);

    return $text;
  }

}
