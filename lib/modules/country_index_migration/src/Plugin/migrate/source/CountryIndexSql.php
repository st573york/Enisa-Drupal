<?php

namespace Drupal\country_index_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "country_index_sql"
 * )
 */
class CountryIndexSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indices', 'ins')
      ->fields('ins', ['id', 'name', 'description', 'status_id', 'json_data', 'report_json', 'report_date']);

    $query->leftJoin('countries', 'cs', 'ins.country_id = cs.id');
    $query->leftJoin('index_configurations', 'ic', 'ins.index_configuration_id = ic.id');

    // Aliasing fields.
    $query->addField('cs', 'name', 'country_name');
    $query->addField('ic', 'name', 'index_name');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('Country index name'),
      'description' => $this->t('Country index description'),
      'status_id' => $this->t('Country index state'),
      'json_data' => $this->t('Country index json data'),
      'report_json' => $this->t('Country index report json data'),
      'report_date' => $this->t('Country index report date'),
      'country_name' => $this->t('Country name'),
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
        'alias' => 'ins',
      ],
    ];
  }

}
