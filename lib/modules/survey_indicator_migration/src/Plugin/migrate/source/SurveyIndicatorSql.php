<?php

namespace Drupal\survey_indicator_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "survey_indicator_sql"
 * )
 */
class SurveyIndicatorSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('survey_indicators', 'si')
      ->fields('si', [
        'id',
        'state_id',
        'dashboard_state_id',
        'rating',
        'comments',
        'answers_loaded',
        'deadline',
        'last_saved',
      ]);

    $query->leftJoin('questionnaire_countries', 'qc', 'si.questionnaire_country_id = qc.id');
    $query->leftJoin('questionnaires', 'qs', 'qc.questionnaire_id = qs.id');
    $query->leftJoin('countries', 'cs', 'qc.country_id = cs.id');
    $query->leftJoin('indicators', 'is', 'si.indicator_id = is.id');
    $query->leftJoin('users', 'usa', 'si.assignee = usa.id');
    $query->leftJoin('users', 'usab', 'si.approved_by = usab.id');

    // Aliasing fields.
    $query->addField('qs', 'title', 'questionnaire_name');
    $query->addField('cs', 'name', 'country_name');
    $query->addField('is', 'name', 'indicator_name');
    $query->addField('is', 'identifier', 'indicator_identifier');
    $query->addField('is', 'year', 'indicator_year');
    $query->addField('usa', 'email', 'assignee_email');
    $query->addField('usab', 'email', 'approved_by_email');

    $expression = "CONCAT(qs.title, ' - ', cs.name, ' - ', is.identifier)";
    $query->addExpression($expression, 'survey_indicator_title');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'state_id' => $this->t('Survey indicator state'),
      'dashboard_state_id' => $this->t('Survey indicator dashboard state'),
      'rating' => $this->t('Survey indicator rating'),
      'comments' => $this->t('Survey indicator comments'),
      'answers_loaded' => $this->t('Survey indicator answers loaded'),
      'deadline' => $this->t('Survey indicator deadline'),
      'last_saved' => $this->t('Survey indicator last saved'),
      'questionnaire_name' => $this->t('Questionnaire name'),
      'country_name' => $this->t('Country name'),
      'indicator_name' => $this->t('Indicator name'),
      'indicator_identifier' => $this->t('Indicator identifier'),
      'indicator_year' => $this->t('Indicator year'),
      'assignee_email' => $this->t('Assignee email'),
      'approved_by_email' => $this->t('Approved by email'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'si',
      ],
    ];
  }

}
