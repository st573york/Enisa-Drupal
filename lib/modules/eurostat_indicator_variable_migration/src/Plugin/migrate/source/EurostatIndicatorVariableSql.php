<?php

namespace Drupal\eurostat_indicator_variable_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "eurostat_indicator_variable_sql"
 * )
 */
class EurostatIndicatorVariableSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('eurostat_indicator_variables', 'eiv')
      ->fields('eiv', [
        'id',
        'eurostat_indicator_id',
        'variable_identifier',
        'variable_code',
        'variable_name',
        'variable_value',
      ]);

    $query->leftJoin('countries', 'cs', 'eiv.country_id = cs.id');

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
      'eurostat_indicator_id' => $this->t('Eurostat indicator'),
      'variable_identifier' => $this->t('Eurostat indicator variable identifier'),
      'variable_code' => $this->t('Eurostat indicator variable code'),
      'variable_name' => $this->t('Eurostat indicator variable name'),
      'variable_value' => $this->t('Eurostat indicator variable value'),
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
        'alias' => 'eiv',
      ],
    ];
  }

}
