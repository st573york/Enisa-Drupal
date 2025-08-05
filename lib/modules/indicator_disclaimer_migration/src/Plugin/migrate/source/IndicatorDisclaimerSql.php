<?php

namespace Drupal\indicator_disclaimer_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_disclaimer_sql"
 * )
 */
class IndicatorDisclaimerSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_disclaimers', 'id')
      ->fields('id', [
        'id',
        'what_100_means_eu',
        'what_100_means_ms',
        'frac_max_norm',
        'rank_norm',
        'target_100',
        'target_75',
        'direction',
        'new_indicator',
        'min_max_0037_1',
        'min_max',
      ]);

    $query->leftJoin('indicators', 'is', 'id.indicator_id = is.id');

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
      'what_100_means_eu' => $this->t('Indicator disclaimer what 100 means eu'),
      'what_100_means_ms' => $this->t('Indicator disclaimer what 100 means ms'),
      'frac_max_norm' => $this->t('Indicator disclaimer frac max norm'),
      'rank_norm' => $this->t('Indicator disclaimer rank norm'),
      'target_100' => $this->t('Indicator disclaimer target 100'),
      'target_75' => $this->t('Indicator disclaimer target 75'),
      'direction' => $this->t('Indicator disclaimer direction'),
      'new_indicator' => $this->t('Indicator disclaimer new indicator'),
      'min_max_0037_1' => $this->t('Indicator disclaimer min max 0037 1'),
      'min_max' => $this->t('Indicator disclaimer min max'),
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
        'alias' => 'id',
      ],
    ];
  }

}
