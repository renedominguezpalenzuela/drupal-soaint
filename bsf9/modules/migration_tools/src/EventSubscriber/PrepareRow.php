<?php

namespace Drupal\migration_tools\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\migration_tools\Message;
use Drupal\migration_tools\Modifier\DomModifier;
use Drupal\migration_tools\Modifier\SourceModifierHtml;
use Drupal\migration_tools\Obtainer\Job;
use Drupal\migration_tools\Operations;
use Drupal\migration_tools\Redirects;
use Drupal\migration_tools\SourceParser\HtmlBase;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modify raw data on import.
 */
class PrepareRow implements EventSubscriberInterface {

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
   * The URL of the document to retrieve.
   *
   * @var string
   */
  protected $url;

   /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RedirectRepository $redirect_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->redirectRepository = $redirect_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PREPARE_ROW] = 'onMigratePrepareRow';
    return $events;
  }

  /**
   * Callback function for prepare row migration event.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare row event.
   */
  public function onMigratePrepareRow(MigratePrepareRowEvent $event) {
    $row = $event->getRow();

    $migration_tools_settings = $row->getSourceProperty('migration_tools');

    if (!empty($migration_tools_settings)) {
      // Pass these into the row for use within operations..
      $row->entityTypeManager = $this->entityTypeManager;
      $row->redirectRepository = $this->redirectRepository;
      // This triggers all of migration tools to do its thing..
      Operations::process($migration_tools_settings, $row);
    }
  }

}
