<?php

namespace Drupal\indicator_accordion_question_option_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_accordion_question_option_sql"
 * )
 */
class IndicatorAccordionQuestionOptionSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_accordion_question_options', 'iaqo')
      ->fields('iaqo', ['id', 'text', 'master', 'score', 'value']);

    $query->leftJoin('indicator_accordion_questions', 'iaq', 'iaqo.question_id = iaq.id');
    $query->leftJoin('indicator_accordions', 'ia', 'iaq.accordion_id = ia.id');
    $query->leftJoin('indicators', 'is', 'ia.indicator_id = is.id');

    // Aliasing fields.
    $query->addField('ia', 'title', 'indicator_accordion_name');
    $query->addField('ia', 'order', 'indicator_accordion_order');
    $query->addField('iaq', 'title', 'indicator_accordion_question_name');
    $query->addField('iaq', 'order', 'indicator_accordion_question_order');
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
      'text' => $this->t('Indicator accordion question option text'),
      'master' => $this->t('Indicator accordion question option master'),
      'score' => $this->t('Indicator accordion question option score'),
      'value' => $this->t('Indicator accordion question option value'),
      'indicator_accordion_name' => $this->t('Indicator accordion name'),
      'indicator_accordion_order' => $this->t('Indicator accordion order'),
      'indicator_accordion_question_name' => $this->t('Indicator accordion question name'),
      'indicator_accordion_question_order' => $this->t('Indicator accordion question order'),
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
        'alias' => 'iaqo',
      ],
    ];
  }

}
