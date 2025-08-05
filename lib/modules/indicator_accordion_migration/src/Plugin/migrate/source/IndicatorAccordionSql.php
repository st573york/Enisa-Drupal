<?php

namespace Drupal\indicator_accordion_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_accordion_sql"
 * )
 */
class IndicatorAccordionSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_accordions', 'ia')
      ->fields('ia', ['id', 'title', 'order']);

    $query->leftJoin('indicators', 'is', 'ia.indicator_id = is.id');

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
      'title' => $this->t('Indicator accordion title'),
      'order' => $this->t('Indicator accordion order'),
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
        'alias' => 'ia',
      ],
    ];
  }

}
