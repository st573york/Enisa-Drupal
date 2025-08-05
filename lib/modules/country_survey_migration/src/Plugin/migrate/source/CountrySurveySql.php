<?php

namespace Drupal\country_survey_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "country_survey_sql"
 * )
 */
class CountrySurveySql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('questionnaire_countries', 'qc')
      ->fields('qc', ['id', 'completed', 'submitted_at', 'requested_changes_submitted_at']);

    $query->leftJoin('users', 'usda', 'qc.default_assignee = usda.id');
    $query->leftJoin('users', 'ussb', 'qc.submitted_by = ussb.id');
    $query->leftJoin('users', 'usab', 'qc.approved_by = usab.id');
    $query->leftJoin('questionnaires', 'qs', 'qc.questionnaire_id = qs.id');
    $query->leftJoin('countries', 'cs', 'qc.country_id = cs.id');

    // Aliasing fields.
    $query->addField('usda', 'email', 'default_assignee_email');
    $query->addField('ussb', 'email', 'submitted_by_email');
    $query->addField('usab', 'email', 'approved_by_email');
    $query->addField('qs', 'title', 'survey_name');
    $query->addField('cs', 'name', 'country_name');

    $expression = "CONCAT(cs.name, ' - ', qs.title)";
    $query->addExpression($expression, 'country_survey_title');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'completed' => $this->t('Questionnaire country completed'),
      'submitted_at' => $this->t('Questionnaire country submitted at'),
      'requested_changes_submitted_at' => $this->t('Questionnaire country requested changes submitted at'),
      'default_assignee_email' => $this->t('Default assignee user email'),
      'submitted_by_email' => $this->t('Submitted by user email'),
      'approved_by_email' => $this->t('Approved by user email'),
      'survey_name' => $this->t('Survey name'),
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
        'alias' => 'qc',
      ],
    ];
  }

}
