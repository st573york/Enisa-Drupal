<?php

namespace Drupal\user_migration\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User Lookup process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "user_lookup"
 * )
 */
final class UserLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Creates an instance of the plugin.
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $query = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE);

    if (isset($this->configuration['username'])) {
      $query->condition('name', $this->configuration['username']);
    }
    else {
      $query->condition('mail', $value);
    }

    $entities = $query->execute();

    if (!empty($entities)) {
      return reset($entities);
    }

    return NULL;
  }

}
