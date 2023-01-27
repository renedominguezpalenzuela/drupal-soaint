<?php

namespace Drupal\migration_tools\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migration_tools\Redirects;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modify raw data on import.
 */
class PostRowSave implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\redirect\RedirectRepository definition.
   *
   * @var \Drupal\redirect\RedirectRepository
   */
  protected $redirectRepository;

  /**
   * Drupal\migrate_plus\Plugin\MigrationConfigEntityPluginManager definition.
   *
   * @var \Drupal\migrate_plus\Plugin\MigrationConfigEntityPluginManager
   */
  protected $migrationConfigEntityPluginManager;

  /**
   * The URL of the document to retrieve.
   *
   * @var string
   */
  protected $url;


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE] = 'onMigratePostRowSave';
    return $events;
  }

  /**
   * Callback function for prepare row migration event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The prepare row event.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $row = $event->getRow();

    // Save any redirects if any have accumulated.
    if (!empty($row->MTRedirector) && $row->MTRedirector instanceof Redirects) {
      //MTRedirector exists, so save any redirects.
      $nids = $event->getDestinationIdValues();
      $nid = reset($nids);
      $row->MTRedirector->saveRedirects($nid);
    }
  }

}
