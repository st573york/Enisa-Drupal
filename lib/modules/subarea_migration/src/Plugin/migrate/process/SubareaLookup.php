<?php

namespace Drupal\subarea_migration\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subarea Lookup process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "subarea_lookup"
 * )
 */
final class SubareaLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The entity type manager service.
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
    $additional_fields = $this->configuration['additional_fields'];

    $subarea_name = $row->getSourceProperty($additional_fields['subarea_name']) ?? '';
    $subarea_identifier = $row->getSourceProperty($additional_fields['subarea_identifier']) ?? '';
    $year = $row->getSourceProperty($additional_fields['year']) ?? '';

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'subarea',
        'title' => $subarea_name,
        'field_identifier' => $subarea_identifier,
        'field_year' => $year,
      ]);

    if (!empty($entities)) {
      $entity = reset($entities);

      return $entity->id();
    }

    return NULL;
  }

}
