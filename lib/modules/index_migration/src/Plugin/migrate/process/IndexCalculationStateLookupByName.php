<?php

namespace Drupal\index_migration\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;

/**
 * Index Calculation State Lookup By Name process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "index_calculation_state_lookup_by_name"
 * )
 */
class IndexCalculationStateLookupByName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $connection = Database::getConnection('default', 'enisa');

    // Execute a query in the migration database.
    $query = $connection->select('tasks', 'ts')
      ->fields('ts', ['status_id'])
      ->condition('ts.type', 'IndexCalculation')
      ->condition('ic.name', $value);

    $query->leftJoin('index_configurations', 'ic', 'ts.index_configuration_id = ic.id');

    $migration_task = $query->execute()->fetch();

    if ($migration_task) {
      switch ($migration_task->status_id) {
        case '2':
          return 'completed';

        case '3':
          return 'failed';

        default:
          return NULL;
      }
    }

    return NULL;
  }

}
