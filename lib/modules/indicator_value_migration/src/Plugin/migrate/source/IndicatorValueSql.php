<?php

namespace Drupal\indicator_value_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_value_sql"
 * )
 */
class IndicatorValueSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_values', 'iv')
      ->fields('iv', ['id', 'value', 'year']);

    $query->leftJoin('indicators', 'is', 'iv.indicator_id = is.id');
    $query->leftJoin('countries', 'cs', 'iv.country_id = cs.id');

    // Aliasing fields.
    $query->addField('is', 'name', 'indicator_name');
    $query->addField('is', 'identifier', 'indicator_identifier');
    $query->addField('is', 'year', 'indicator_year');
    $query->addField('cs', 'name', 'country_name');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'value' => $this->t('Indicator value'),
      'year' => $this->t('Indicator value year'),
      'indicator_name' => $this->t('Indicator name'),
      'indicator_identifier' => $this->t('Indicator identifier'),
      'indicator_year' => $this->t('Indicator year'),
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
        'alias' => 'iv',
      ],
    ];
  }

}
