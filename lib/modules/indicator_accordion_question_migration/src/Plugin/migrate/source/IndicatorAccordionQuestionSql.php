<?php

namespace Drupal\indicator_accordion_question_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_accordion_question_sql"
 * )
 */
class IndicatorAccordionQuestionSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_accordion_questions', 'iaq')
      ->fields('iaq', [
        'id',
        'title',
        'order',
        'type_id',
        'info',
        'compatible',
        'answers_required',
        'reference_required',
      ]);

    $query->leftJoin('indicator_accordions', 'ia', 'iaq.accordion_id = ia.id');
    $query->leftJoin('indicators', 'is', 'ia.indicator_id = is.id');

    // Aliasing fields.
    $query->addField('ia', 'title', 'indicator_accordion_name');
    $query->addField('ia', 'order', 'indicator_accordion_order');
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
      'title' => $this->t('Indicator accordion question title'),
      'order' => $this->t('Indicator accordion question order'),
      'type_id' => $this->t('Indicator accordion question type'),
      'info' => $this->t('Indicator accordion question info'),
      'compatible' => $this->t('Indicator accordion question compatible'),
      'answers_required' => $this->t('Indicator accordion question answers required'),
      'reference_required' => $this->t('Indicator accordion question reference required'),
      'indicator_accordion_name' => $this->t('Indicator accordion name'),
      'indicator_accordion_order' => $this->t('Indicator accordion order'),
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
        'alias' => 'iaq',
      ],
    ];
  }

}
