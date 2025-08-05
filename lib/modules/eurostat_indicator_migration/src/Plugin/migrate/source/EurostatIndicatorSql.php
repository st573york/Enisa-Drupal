<?php

namespace Drupal\eurostat_indicator_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "eurostat_indicator_sql"
 * )
 */
class EurostatIndicatorSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('eurostat_indicators', 'ei')
      ->fields('ei', ['id', 'name', 'source', 'identifier', 'report_year', 'value']);

    $query->leftJoin('countries', 'cs', 'ei.country_id = cs.id');

    // Aliasing fields.
    $query->addField('cs', 'name', 'country_name');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('Eurostat indicator name'),
      'source' => $this->t('Eurostat indicator source'),
      'identifier' => $this->t('Eurostat indicator identifier'),
      'report_year' => $this->t('Eurostat indicator report year'),
      'value' => $this->t('Eurostat indicator value'),
      'country_name' => $this->t('Country name'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'ei',
      ],
    ];
  }

}
