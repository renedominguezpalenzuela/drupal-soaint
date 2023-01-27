<?php

namespace Drupal\migration_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Creates the paragraph entity revision with specified values.
 *
 * @MigrateProcessPlugin(
 * id = "create_default_paragraph_revision",
 * handle_multiples = TRUE
 * )
 *
 * Example usage: A single depth paragraph entity revision of paragraph type
 *  'paint_recommendation'.
 * @code
 * field_some_paragraph_entity_ref_revison:
 *   plugin: create_default_paragraph_revision
 *   paragraph_default:
 *     create_paragraph_bundle: paint_recommendation
 *     field_color: blue
 *     field_paint_type: latex
 *     field_coats: 2
 *     field_exterior_use: true
 *
 * @endcode
 *
 * Example usage: A paragraph of paragraph type
 *  'paint_recommendation'with a sub-paragraph of type 'brush_style'.
 * @code
 * field_some_paragraph_entity_ref_revision:
 *   plugin: create_default_paragraph_revision
 *   paragraph_default:
 *     create_paragraph_bundle: paint_recommendation
 *     field_color: blue
 *     field_paint_type: latex
 *     field_coats: 2
 *     field_exterior_use: true
 *     field_brush_recommended:
 *       create_paragraph_bundle: brush_style
 *       field_size: '4 cm'
 *       field_bristle_type: nylon
 *
 * @endcode
 */
class CreateDefaultParagraphRevision extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // The $value is not used for anything as this is not intended to be
    // read from a source property.
    $paragraph_data = $this->getDefaultData();
    $bundle = $this->getParagraphBundleToBuild($paragraph_data);
    if ($bundle) {
      return $this->createParagraphRevision($bundle, $paragraph_data);
    }

    // Something went wrong.
    throw new \Exception("Failed to create a default paragraph entity reference for field '$destination_property' in 'create_default_paragraph_revision' process plugin.");
  }

  /**
   * Creates a new paragraph entity revision and returns the pid.
   *
   * Can be called recursively to create paragraphs within paragraphs.
   *
   * @param string $bundle
   *   The name of the paragraph bundle to create.
   * @param array $field_values
   *   The field name value pairs that should populate the paragraph.
   *
   * @return int|null
   *   The paragraph id if one was created.  NULL otherwise.
   */
  protected function createParagraphRevision(string $bundle, array $field_values) {
    $paragraph_revision = NULL;
    // Check to see if this is a known bundle.
    if ($bundle) {
      foreach ($field_values as $field_name => $value) {
        if ($bundle_new = $this->getParagraphBundleToBuild($value)) {
          // This is a sub-paragraph we have to create.
          $field_values[$field_name] = $this->createParagraphRevision($bundle_new, $value);
        }
      }

      $paragraph = Paragraph::create($field_values);
      $paragraph->save();

      $paragraph_revision = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    return $paragraph_revision;
  }

  /**
   * Checks if a bundle is a valid paragraph bundle.
   *
   * @param string $bundle
   *   The paragraph bundle to be created.
   *
   * @return bool
   *   TRUE if the paragraph bundle exists.
   *
   * @throws \Exception
   *   When a bundle is specified that does not exist.
   */
  protected function bundleExists(string $bundle) : bool {
    $available_paragraphs = ParagraphsType::loadMultiple();
    if (isset($available_paragraphs[$bundle])) {
      return TRUE;
    }
    // The paragraph type does not exist.  Throw exception.
    throw new \Exception("Could not create a default paragraph of type '$bundle' in 'create_default_paragraph_revision' process plugin.");
  }

  /**
   * Validates and returns the default paragraph data from migration config.
   *
   * @return array
   *   The array of data to be used to create the default paragraph.
   *
   * @throws \InvalidArgumentException
   *   If the data is not present.
   */
  protected function getDefaultData() {
    if (isset($this->configuration['paragraph_default'])) {
      if (!empty($this->configuration['paragraph_default']) && is_array($this->configuration['paragraph_default'])) {
        return $this->configuration['paragraph_default'];
      }
      else {
        // The required 'paragraph_default' must be an array with data.
        throw new \InvalidArgumentException("The element `paragraph_default` ben an array with field data to use the `create_default_paragraph_revision` process plugin.");
      }
    }
    else {
      // The required 'paragraph_default' is not defined, throw an exception.
      throw new \InvalidArgumentException("The element `paragraph_default` must be defined to use the `create_default_paragraph_revision` process plugin.");
    }
  }

  /**
   * Gets the bundle of the paragraph to create if it exists.
   *
   * @param mixed $values
   *   The valuse that should be added to the paragraph.
   *
   * @return string|null
   *   The paragraph bundle type to be created.  NULL if nothing to create.
   */
  protected function getParagraphBundleToBuild(&$values) {
    $bundle = NULL;
    if (!empty($values['create_paragraph_bundle'])) {
      $bundle = $values['create_paragraph_bundle'];
      $this->bundleExists($bundle);
      // Remove bundle from array so it does not get processed as a field.
      unset($values['create_paragraph_bundle']);
      // Put the bundle in as 'type' where it belongs.
      $values['type'] = $bundle;

    }
    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
