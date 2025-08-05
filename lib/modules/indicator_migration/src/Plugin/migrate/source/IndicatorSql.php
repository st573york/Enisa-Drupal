<?php

namespace Drupal\indicator_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_sql"
 * )
 */
class IndicatorSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicators', 'ins')
      ->fields('ins', [
        'id',
        'order',
        'validated',
        'name',
        'short_name',
        'description',
        'default_weight',
        'algorithm',
        'source',
        'comment',
        'identifier',
        'report_year',
        'category',
        'year',
        'clone_year',
      ]);

    $query->leftJoin('subareas', 'su', 'ins.default_subarea_id = su.id');

    // Aliasing fields.
    $query->addField('su', 'name', 'subarea_name');
    $query->addField('su', 'identifier', 'subarea_identifier');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'order' => $this->t('Indicator order'),
      'validated' => $this->t('Indicator validated'),
      'name' => $this->t('Indicator name'),
      'short_name' => $this->t('Indicator short name'),
      'description' => $this->t('Indicator description'),
      'default_weight' => $this->t('Indicator default weight'),
      'algorithm' => $this->t('Indicator algorithm'),
      'source' => $this->t('Indicator source'),
      'comment' => $this->t('Indicator comment'),
      'identifier' => $this->t('Indicator identifier'),
      'report_year' => $this->t('Indicator report year'),
      'category' => $this->t('Indicator category'),
      'year' => $this->t('Indicator year'),
      'clone_year' => $this->t('Indicator clone year'),
      'subarea_name' => $this->t('Indicator default subarea name'),
      'subarea_identifier' => $this->t('Indicator default subarea identifier'),
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
