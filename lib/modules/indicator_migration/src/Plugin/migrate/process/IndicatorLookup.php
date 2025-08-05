<?php

namespace Drupal\indicator_migration\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indicator Lookup process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "indicator_lookup"
 * )
 */
final class IndicatorLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Function __construct.
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Function create.
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

    $indicator_name = $row->getSourceProperty($additional_fields['indicator_name']) ?? '';
    $indicator_identifier = $row->getSourceProperty($additional_fields['indicator_identifier']) ?? '';
    $indicator_year = $row->getSourceProperty($additional_fields['indicator_year']) ?? '';

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'indicator',
        'title' => $indicator_name,
        'field_identifier' => $indicator_identifier,
        'field_year' => $indicator_year,
      ]);

    if (!empty($entities)) {
      $entity = reset($entities);

      return $entity->id();
    }

    return NULL;
  }

}
