<?php

namespace Drupal\indicator_migration\Plugin\migrate\process;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indicator State Lookup process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "indicator_state_lookup"
 * )
 */
final class IndicatorStateLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {
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
    if (empty($value)) {
      return NULL;
    }

    $term = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'indicator_states', $value - 1, 'field_state_id');

    if (!is_null($term)) {
      return $term->id();
    }

    return NULL;
  }

}
