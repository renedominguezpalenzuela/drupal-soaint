<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Class ObtainLinks
 *
 * Contains logic for parsing for links in HTML.
 */
class ObtainLink extends ObtainHtml {

  /**
   * Find links in contents of the selector and put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   *
   * @return array
   *   The array of elements found containing element, href, link_text, base_uri
   */
  protected function findLinks($selector) {
    return $this->pluckLinks($selector, FALSE);
  }

  /**
   * Pluck the links in contents of the selector, put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return array
   *   The array of elements found containing element, href, link_text, base_uri
   */
  protected function pluckLinks($selector, $pluck = TRUE) {
    $found = [];
    if (!empty($selector)) {
      $element_with_links = $this->queryPath->find($selector);
      // Get HREF links.
      $elements = $element_with_links->find('a');
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        if ($element->hasAttr('href')) {
          $href = $element->attr('href');
          $link_text = trim($element->get(0)->textContent);
          $base_uri = $element->get(0)->baseURI;

          $found[] = [
            'element' => $element,
            'href' => $href,
            'link_text' => $link_text,
            'base_uri' => $base_uri,
          ];
        }
        $this->setCurrentFindMethod("pluckLinks($selector" . ')');
      }
      if ($pluck) {
        $this->setElementToRemove($elements);
      }
    }

    return $found;
  }

  /**
   * Find links in contents of the selector and return their hrefs.
   *
   * @param string $selector
   *   The selector to find.
   *
   * @return array
   *   The array of hrefs found
   */
  protected function findLinksHref($selector) {
    return $this->pluckLinksHref($selector, FALSE);
  }

  /**
   * Pluck links in contents of the selector and return their hrefs.
   *
   * @param string $selector
   *   The selector to find.
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return array
   *   The array of hrefs found
   */
  protected function pluckLinksHref($selector, $pluck = TRUE) {
    $links = $this->pluckLinks($selector, $pluck);
    $hrefs = [];
    if ($links) {
      foreach ($links as $link) {
        $hrefs[$link['href']] = $link['href'];
      }
    }
    return array_keys($hrefs);
  }

  /**
   * Validate links array.
   *
   * @param array $links
   *   Array of links.
   *
   * @return array
   *   Array containing only valid links.
   */
  protected function validateLinks(array &$links) {
    // Just return all the links here, no validation required.
    return $links;
  }

  /**
   * Evaluates $found array and if it checks out, returns TRUE.
   *
   * This method is misleadingly named since it is processing an array, but
   * must override the string based validateString.
   *
   * @param mixed $found
   *   The array to validate.
   *
   * @return bool
   *   TRUE if array is usuable. FALSE if it isn't.
   */
  protected function validateString($found) {
    // Run through any evaluations. If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case empty($found):
      case !is_array($found):

        return FALSE;

      default:
        return TRUE;
    }
  }

  /**
   * Cleans array and returns it prior to validation.
   *
   * This method is misleadingly named since it is processing an array, but
   * must override the string based cleanString.
   *
   * @param mixed $found
   *   Text to clean and return.
   *
   * @return mixed
   *   The cleaned array.
   */
  public static function cleanString($found) {
    $found = (empty($found)) ? [] : $found;
    // Make sure it is an array, just in case someone uses a string finder.
    $found = (is_array($found)) ? $found : [$found];

    return $found;
  }

}
