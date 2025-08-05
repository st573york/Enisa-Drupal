<?php

namespace Drupal\survey_indicator_answer_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "survey_indicator_answer_sql"
 * )
 */
class SurveyIndicatorAnswerSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('survey_indicator_answers', 'sia')
      ->fields('sia', [
        'id',
        'survey_indicator_id',
        'question_id',
        'choice_id',
        'free_text',
        'reference_year',
        'reference_source',
        'last_saved',
      ]);

    $query->leftJoin('survey_indicators', 'si', 'sia.survey_indicator_id = si.id');
    $query->leftJoin('questionnaire_countries', 'qc', 'si.questionnaire_country_id = qc.id');
    $query->leftJoin('questionnaires', 'qs', 'qc.questionnaire_id = qs.id');
    $query->leftJoin('countries', 'cs', 'qc.country_id = cs.id');
    $query->leftJoin('indicators', 'is', 'si.indicator_id = is.id');
    $query->leftJoin('indicator_accordion_questions', 'iaq', 'sia.question_id = iaq.id');
    $query->leftJoin('indicator_accordions', 'ia', 'iaq.accordion_id = ia.id');

    // Aliasing fields.
    $query->addField('qs', 'title', 'questionnaire_name');
    $query->addField('cs', 'name', 'country_name');
    $query->addField('is', 'name', 'indicator_name');
    $query->addField('is', 'identifier', 'indicator_identifier');
    $query->addField('is', 'year', 'indicator_year');
    $query->addField('ia', 'title', 'indicator_accordion_name');
    $query->addField('ia', 'order', 'indicator_accordion_order');
    $query->addField('iaq', 'title', 'indicator_accordion_question_name');
    $query->addField('iaq', 'order', 'indicator_accordion_question_order');

    $expression = "CONCAT(qs.title, ' - ', cs.name, ' - ', is.identifier, ' - ', ia.order, ' - ', iaq.order)";
    $query->addExpression($expression, 'survey_indicator_answer_title');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'survey_indicator_id' => $this->t('Survey indicator id'),
      'question_id' => $this->t('Survey indicator question id'),
      'choice_id' => $this->t('Survey indicator answer choice'),
      'free_text' => $this->t('Survey indicator answer free text'),
      'reference_year' => $this->t('Survey indicator answer reference year'),
      'reference_source' => $this->t('Survey indicator answer reference source'),
      'last_saved' => $this->t('Survey indicator answer last saved'),
      'questionnaire_name' => $this->t('Questionnaire name'),
      'country_name' => $this->t('Country name'),
      'indicator_name' => $this->t('Indicator name'),
      'indicator_identifier' => $this->t('Indicator identifier'),
      'indicator_year' => $this->t('Indicator year'),
      'indicator_accordion_name' => $this->t('Indicator accordion name'),
      'indicator_accordion_order' => $this->t('Indicator accordion order'),
      'indicator_accordion_question_name' => $this->t('Indicator accordion question name'),
      'indicator_accordion_question_order' => $this->t('Indicator accordion question order'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'sia',
      ],
    ];
  }

}
