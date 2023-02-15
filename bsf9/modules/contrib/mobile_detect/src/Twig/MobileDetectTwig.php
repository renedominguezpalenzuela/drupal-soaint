<?php

namespace Drupal\mobile_detect\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Detection\MobileDetect;

/**
 * MobileDetectTwig class.
 *
 */
class MobileDetectTwig extends AbstractExtension 
{

  /**
   * Mobile detect service.
   *
   * @var \Detection\MobileDetect
   */
	protected $mobileDetector;

	/**
	 * Constructor
	 * @param MobileDetect $mobileDetector
	 */
	public function __construct(MobileDetect $mobileDetector) {
		$this->mobileDetector = $mobileDetector;
	}

	/**
	 * Get extension twig function
	 * @return array
	 */
	public function getFunctions() {
		return [
			new TwigFunction('is_mobile', [$this, 'isMobile']),
			new TwigFunction('is_tablet', [$this, 'isTablet']),
			new TwigFunction('is_device', [$this, 'isDevice']),
			new TwigFunction('is_ios', [$this, 'isIOS']),
			new TwigFunction('is_android_os', [$this, 'isAndroidOS'])
		];
	}

	/**
	 * Is mobile
	 * @return boolean
	 */
	public function isMobile() {
		return $this->mobileDetector->isMobile();
	}

	/**
	 * Is tablet
	 * @return boolean
	 */
	public function isTablet() {
		return $this->mobileDetector->isTablet();
	}

	/**
	 * Is device
	 * @param string $deviceName is[iPhone|BlackBerry|HTC|Nexus|Dell|Motorola|Samsung|Sony|Asus|Palm|Vertu|...]
	 * @return boolean
	 */
	public function isDevice($deviceName) {
		$methodName = 'is' . strtolower((string) $deviceName);

		return $this->mobileDetector->$methodName();
	}

	/**
	 * Is iOS
	 * @return boolean
	 */
	public function isIOS() {
		return $this->mobileDetector->isIOS();
	}

	/**
	 * Is Android OS
	 * @return boolean
	 */
	public function isAndroidOS() {
		return $this->mobileDetector->isAndroidOS();
	}

	/**
	 * Extension name
	 * @return string
	 */
	public function getName() {
		return 'mobile_detect.twig.extension';
	}

}
