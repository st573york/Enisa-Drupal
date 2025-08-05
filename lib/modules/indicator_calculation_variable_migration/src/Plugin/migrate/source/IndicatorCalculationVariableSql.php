<?php

namespace Drupal\indicator_calculation_variable_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_calculation_variable_sql"
 * )
 */
class IndicatorCalculationVariableSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_calculation_variables', 'icv')
      ->fields('icv', [
        'id',
        'question_id',
        'algorithm',
        'type',
        'neutral_score',
        'predefined_divider',
        'normalize',
        'inverse_value',
        'custom_function_name',
      ]);

    $query->leftJoin('indicators', 'is', 'icv.indicator_id = is.id');

    // Aliasing fields.
    $query->addField('is', 'name', 'indicator_name');
    $query->addField('is', 'identifier', 'indicator_identifier');
    $query->addField('is', 'year', 'indicator_year');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'question_id' => $this->t('Indicator calculation variable question'),
      'algorithm' => $this->t('Indicator calculation variable algorithm'),
      'type' => $this->t('Indicator calculation variable type'),
      'neutral_score' => $this->t('Indicator calculation variable neutral score'),
      'predefined_divider' => $this->t('Indicator calculation variable predefined divider'),
      'normalize' => $this->t('Indicator calculation variable normalize'),
      'inverse_value' => $this->t('Indicator calculation variable inverse value'),
      'custom_function_name' => $this->t('Indicator calculation variable custom function name'),
      'indicator_name' => $this->t('Indicator name'),
      'indicator_identifier' => $this->t('Indicator identifier'),
      'indicator_year' => $this->t('Indicator year'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'icv',
      ],
    ];
  }

}
