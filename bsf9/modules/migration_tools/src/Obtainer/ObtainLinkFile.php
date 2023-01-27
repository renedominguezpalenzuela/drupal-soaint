<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Class ObtainLinkFile
 *
 * Contains logic for parsing for file links in HTML.
 */
class ObtainLinkFile extends ObtainLink {

  /**
   * Find file links in selector contents, put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   *
   * @return array
   *   The array of elements found.
   */
  protected function findFileLinks($selector, array $file_extensions = [], array $domains_to_include = []) {
    return self::pluckFileLinks($selector, $file_extensions, $domains_to_include, FALSE);
  }

  /**
   * Pluck file links in selector contents, put each element in an array.
   *
   * @param string $selector
   *   The selector to pluck.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   * @param bool $pluck
   *   If TRUE, will pluck elements.
   *
   * @return array
   *   The array of elements found.
   */
  protected function pluckFileLinks($selector, array $file_extensions = [], array $domains_to_include = [], $pluck = TRUE) {
    $links = parent::findLinks($selector);
    $valid_links = $links;

    if (!empty($links) && (!empty($file_extensions) || !empty($domains_to_include))) {
      $valid_links = $this->validateLinks($links, $file_extensions, $domains_to_include);
      if ($pluck) {
        foreach ($valid_links as $valid_link) {
          $this->setElementToRemove($valid_link['element']);
        }
      }
    }

    return $valid_links;
  }

  /**
   * Find file links in contents of the selector and return their hrefs.
   *
   * @param string $selector
   *   The selector to find.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   *
   * @return array
   *   The array of hrefs found
   */
  protected function findFileLinksHref($selector, array $file_extensions = [], array $domains_to_include = []) {
    return $this->pluckFileLinksHref($selector, $file_extensions, $domains_to_include, FALSE);
  }

  /**
   * Pluck file links in contents of the selector and return their hrefs.
   *
   * @param string $selector
   *   The selector to pluck.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return array
   *   The array of hrefs found
   */
  protected function pluckFileLinksHref($selector, array $file_extensions = [], array $domains_to_include = [], $pluck = TRUE) {
    $links = $this->pluckFileLinks($selector, $file_extensions, $domains_to_include, $pluck);
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
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   *
   * @return array
   *   Array containing only valid links.
   */
  protected function validateLinks(array &$links, array $file_extensions = [], array $domains_to_include = []) {
    if ($links) {
      foreach ($links as $key => $link) {
        $extension = strtolower(pathinfo($link['href'], PATHINFO_EXTENSION));
        $extension = preg_replace('/#.*/', '', $extension);

        // Use base_uri if set, otherwise use href to get domain.
        if (!empty($link['base_uri'])) {
          $link_domain = strtolower(parse_url($link['base_uri'], PHP_URL_HOST));
        }
        else {
          $link_domain = strtolower(parse_url($link['href'], PHP_URL_HOST));
        }
        if (!empty($file_extensions) && !in_array($extension, $file_extensions)) {
          unset($links[$key]);
        }
        elseif (!empty($domains_to_include)) {
          if (!in_array($link_domain, $domains_to_include)) {
            unset($links[$key]);
          }
        }
      }
    }

    return $links;
  }

}
