<?php

namespace Drupal\blazy\Plugin\Filter;

use Drupal\Component\Utility\Crypt;
use Drupal\blazy\Blazy;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides shared filter utilities.
 */
class BlazyFilterUtil {

  /**
   * Returns a randomized id.
   */
  public static function getId($id = 'blazy-filter') {
    return Blazy::getHtmlId(str_replace('_', '-', $id) . '-' . Crypt::randomBytesBase64(8));
  }

  /**
   * Returns settings for attachments.
   */
  public static function attach(array $settings = []) {
    $all = ['blazy' => TRUE, 'filter' => TRUE, 'ratio' => TRUE] + $settings;
    $all['media_switch'] = $switch = $settings['media_switch'];

    if (!empty($settings[$switch])) {
      $all[$switch] = $settings[$switch];
    }

    return $all;
  }

  /**
   * Returns string between delimiters, or empty if not found.
   */
  public static function getStringBetween($string, $start = '[', $end = ']') {
    $string = ' ' . $string;
    $ini = mb_strpos($string, $start);

    if ($ini == 0) {
      return '';
    }

    $ini += strlen($start);
    $len = mb_strpos($string, $end, $ini) - $ini;
    return trim(substr($string, $ini, $len) ?: '');
  }

  /**
   * Returns the inner HTMLof the DOMElement node.
   *
   * See http://www.php.net/manual/en/class.domelement.php#101243
   */
  public static function getHtml(\DOMElement $node) {
    $text = '';
    foreach ($node->childNodes as $child) {
      if ($child instanceof \DOMElement) {
        $text .= $child->ownerDocument->saveXML($child);
      }
    }
    return $text;
  }

  /**
   * Remove HTML tags from a string.
   */
  public static function unwrap($string, $container = 'blazy', $item = 'item') {
    // Might not be available with self-closing [TAG data="BLAH" /].
    if (mb_strpos($string, "[$item") !== FALSE) {
      $string = self::unwrapItem($string, $item);
    }

    return self::unwrapItem($string, $container);
  }

  /**
   * Unwrap the enclosing tags.
   *
   * @todo recheck any reliable regex.
   */
  public static function unwrapItem($string, $item) {
    $patterns = [
      // Not supported, but for completion [TAG data="BLAH"]A.B.C[/TAG].
      "~(<p\>)\[$item?(.*?)\](.*?)\[/$item\](<\/p>)~",
      // Normal WYSIWYG editor outputs with HTML correction filter enabled:
      // <p>[TAG data="BLAH" /]</p>.
      // <p>[TAG settings="BLAH"]</p>.
      // <p>[/TAG]</p>.
      "~(<p\>)\[(/)?$item(.*?)\](<\/p>)~",
      // Abnormal non-WYSIWYG editor outputs: <p>[/TAG]<br />.
      "~(<p\>)\[(/)?$item(.*?)\](<br \/>)~",
      // Abnormal non-WYSIWYG editor outputs, letfovers: [TAG]</p>.
      "~\[(/)?$item(.*?)\](<\/p>)~",
    ];

    $replacements = [
      "<$item$2>$3</$item>",
      "<$2$item$3>",
      "<$2$item$3>",
      "<$1$item$2>",
    ];

    return preg_replace($patterns, $replacements, $string);
  }

  /**
   * Removes nodes.
   */
  public static function removeNodes(&$nodes) {
    foreach ($nodes as $node) {
      if ($node->parentNode) {
        $node->parentNode->removeChild($node);
      }
    }
  }

  /**
   * Return valid nodes based on the allowed tags.
   */
  public static function validNodes(\DOMDocument $dom, array $allowed_tags = [], $exclude = '') {
    $valid_nodes = [];
    foreach ($allowed_tags as $allowed_tag) {
      $nodes = $dom->getElementsByTagName($allowed_tag);
      if ($nodes->length > 0) {
        foreach ($nodes as $node) {
          if ($exclude && $node->hasAttribute($exclude)) {
            continue;
          }

          $valid_nodes[] = $node;
        }
      }
    }
    return $valid_nodes;
  }

  /**
   * Returns a valid node, excluding blur/ noscript images.
   */
  public static function getValidNode($children) {
    $child = $children->item(0);
    $class = $child->getAttribute('class');
    $is_blur = $class && mb_strpos($class, 'b-blur') !== FALSE;
    $is_bg = $class && mb_strpos($class, 'b-bg') !== FALSE;

    if ($is_blur && !$is_bg) {
      $child = $children->item(1) ?: $child;
    }
    return $child;
  }

  /**
   * Returns a image/ iframe src.
   *
   * Checks if we have a valid file entity, not hard-coded image URL.
   * Prioritize data-src for sub-module filters after Blazy.
   */
  public static function getValidSrc($node) {
    $url = '';

    // Prevents data URI from screwing up.
    $func = function ($input) {
      if ($input) {
        $data_uri = mb_substr($input, 0, 10) === 'data:image';
        if (!$data_uri) {
          return $input;
        }
      }
      return '';
    };

    foreach (['data-src', 'src'] as $key) {
      $src = $node->getAttribute($key);
      $check = $func($src);

      if ($check) {
        $url = $check;
        break;
      }
    }

    // If starts with 2 slashes, it is always external.
    if ($url && mb_substr($url, 0, 2) === '//') {
      // We need to query stored SRC for image dimensions, https is enforced.
      $url = 'https:' . $url;
    }

    return $url;
  }

  /**
   * Returns DOMElement nodes expected to be grid, or slide items.
   */
  public static function getNodes(\DOMDocument $dom, $tag = '//grid') {
    $xpath = new \DOMXPath($dom);

    return $xpath->query($tag);
  }

  /**
   * Returns attributes extracted from a DOMElement if any.
   */
  public static function getAttribute(\DOMElement $node, array $excludes = []) {
    $attributes = [];
    if ($node && $node->attributes->length) {
      foreach ($node->attributes as $attribute) {
        $name = $attribute->nodeName;
        $value = $attribute->nodeValue;
        if ($excludes && in_array($name, $excludes)) {
          continue;
        }
        $attributes[$name] = ($name == 'class') ? [$value] : $value;
      }
    }
    return $attributes ? BlazyAttribute::sanitize($attributes) : [];
  }

  /**
   * Extract grids from the node attribute.
   */
  public static function toGrid(\DOMElement $node, array &$settings) {
    if ($check = $node->getAttribute('grid')) {
      $blazies = $settings['blazies'];
      [$settings['style'], $grid, $settings['visible_items']] = array_pad(array_map('trim', explode(":", $check, 3)), 3, NULL);

      if ($grid) {
        [
          $settings['grid_small'],
          $settings['grid_medium'],
          $settings['grid'],
        ] = array_pad(array_map('trim', explode("-", $grid, 3)), 3, NULL);

        $settings['_grid'] = $is_grid = !empty($settings['style']) && !empty($settings['grid']);
        $blazies->set('is.grid', $is_grid);

        if (!empty($settings['style'])) {
          // Babysits typo due to hardcoding. The expected is flex, not flexbox.
          if ($settings['style'] == 'flexbox') {
            $settings['style'] = 'flex';
          }
          Grid::toNativeGrid($settings);
        }
      }
    }
  }

}
