<?php

namespace Drupal\blazy\Dejavu;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyVideoBase is deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Base class for blazy video embed field formatters.
 *
 * This file is no longer used nor needed, and will be removed at 3.x.
 * VEF will continue working via BlazyOEmbed instead.
 *
 * BVEF can take over this file to be compat with Blazy 3.x rather than keeping
 * 1.x debris. Also to adopt core OEmbed security features at ease.
 *
 * This means Slick Video which depends on VEF is deprecated for Slick Media
 * at Blazy 8.2.x with core Media only.
 */
abstract class BlazyVideoBase extends FormatterBase {

  use BlazyFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::extendedSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element    = [];
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);

    $this->admin()->buildSettingsForm($element, $definition);
    $element['media_switch']['#options']['media'] = $this->t('Image to iFrame');

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'background'        => TRUE,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'multimedia'        => TRUE,
      'thumb_positions'   => TRUE,
      'nav'               => TRUE,
    ];
  }

  /**
   * Returns the optional VEF service to avoid dependency for optional plugins.
   */
  protected function vefProviderManager() {
    return Blazy::service('video_embed_field.provider_manager');
  }

}
