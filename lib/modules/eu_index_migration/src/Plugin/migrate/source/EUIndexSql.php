<?php

namespace Drupal\eu_index_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "eu_index_sql"
 * )
 */
class EUIndexSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('baseline_indices', 'bs')
      ->fields('bs', ['id', 'name', 'description', 'json_data', 'report_json', 'report_date']);

    $query->leftJoin('index_configurations', 'ic', 'bs.index_configuration_id = ic.id');

    // Aliasing fields.
    $query->addField('ic', 'name', 'index_name');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('EU index name'),
      'description' => $this->t('EU index description'),
      'json_data' => $this->t('EU index json data'),
      'report_json' => $this->t('EU index report json data'),
      'report_date' => $this->t('EU index report date'),
      'index_name' => $this->t('Index name'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'bs',
      ],
    ];
  }

}
