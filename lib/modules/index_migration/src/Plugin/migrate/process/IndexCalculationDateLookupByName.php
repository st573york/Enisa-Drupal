<?php

namespace Drupal\index_migration\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;

/**
 * Index Calculation Date Lookup By Name process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "index_calculation_date_lookup_by_name"
 * )
 */
class IndexCalculationDateLookupByName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $connection = Database::getConnection('default', 'enisa');

    // Execute a query in the migration database.
    $query = $connection->select('tasks', 'ts')
      ->fields('ts', ['payload'])
      ->condition('ts.type', 'IndexCalculation')
      ->condition('ic.name', $value);

    $query->leftJoin('index_configurations', 'ic', 'ts.index_configuration_id = ic.id');

    $migration_task = $query->execute()->fetch();

    if ($migration_task) {
      $payload = json_decode($migration_task->payload, TRUE);

      return DrupalDateTime::createFromFormat('d-m-Y H:i:s', $payload['last_index_calculation_at'])->getTimestamp();
    }

    return NULL;
  }

}
