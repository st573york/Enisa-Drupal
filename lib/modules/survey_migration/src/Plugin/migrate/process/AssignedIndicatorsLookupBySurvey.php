<?php

namespace Drupal\survey_migration\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assigned Indicators Lookup By Survey process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "assigned_indicators_lookup_by_survey"
 * )
 */
final class AssignedIndicatorsLookupBySurvey extends ProcessPluginBase implements ContainerFactoryPluginInterface {
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
    $drupal_entity_ids = [];
    $questionnaire_id = $value;

    $connection = Database::getConnection('default', 'enisa');

    // Execute a query in the migration database.
    $query = $connection->select('questionnaire_indicators', 'qi')
      ->fields('is', ['name', 'identifier', 'year'])
      ->condition('qi.questionnaire_id', $questionnaire_id);

    $query->leftJoin('indicators', 'is', 'qi.indicator_id = is.id');

    $migration_assigned_indicators = $query->execute()->fetchAll();

    foreach ($migration_assigned_indicators as $migration_assigned_indicator) {
      $entities = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'indicator',
          'title' => $migration_assigned_indicator->name,
          'field_identifier' => $migration_assigned_indicator->identifier,
          'field_year' => $migration_assigned_indicator->year,
        ]);

      if (!empty($entities)) {
        $entity = reset($entities);

        array_push($drupal_entity_ids, [
          'target_id' => $entity->id(),
        ]);
      }
    }

    return $drupal_entity_ids;
  }

}
