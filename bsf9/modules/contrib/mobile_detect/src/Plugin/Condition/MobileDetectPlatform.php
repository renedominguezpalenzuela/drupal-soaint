<?php

namespace Drupal\mobile_detect\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Detection\MobileDetect;

/**
 * Provides the 'Device platform' condition.
 *
 * @Condition(
 *   id = "mobile_detect_platform",
 *   label = @Translation("Device platform")
 * )
 */
class MobileDetectPlatform extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Mobile detect service.
   *
   * @var \Detection\MobileDetect
   */
  protected $mobileDetect;

  /**
   * {@inheritdoc}
   */
	public function __construct(array $configuration, $plugin_id, $plugin_definition, MobileDetect $mobile_detect) {
		parent::__construct($configuration, $plugin_id, $plugin_definition);
		$this->mobileDetect = $mobile_detect;
	}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mobile_detect')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'platform' => [],
      ] + parent::defaultConfiguration();
  }

  /**
	 * {@inheritdoc}
	 */
	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['platform'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('When the platform is determined'),
      '#default_value' => $this->configuration['platform'],
      '#options' => [
        'android' => $this->t('Android'),
        'ios' => $this->t('iOS'),
      ],
      '#description' => $this->t('No platforms will evaluate TRUE for all.'),
		];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
	 * {@inheritdoc}
	 */
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['platform'] = array_filter($form_state->getValue('platform'));
	}

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $platform = implode(', ', $this->configuration['platform']);
    return $this->isNegated()
      ? $this->t('The current platform is not @platform', ['@platform' => $platform])
      : $this->t('The current platform is @platform', ['@platform' => $platform]);
  }

  /**
   * {@inheritdoc}
   */
	public function evaluate() {
    $platform = $this->configuration['platform'];

    if (empty($platform) && !$this->isNegated()) {
      return true;
    }
    
    $detect = $this->mobileDetect;
    foreach ($platform as $value) {
      if (($value === 'android' && $detect->isAndroidOS()) 
        || ($value === 'ios' && $detect->isIOS())) {
        return true;
      }
    }
    return false;
	}

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'headers:User-Agent';
    return $contexts;
  }

}
