<?php

namespace Drupal\indicator_requested_change_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "indicator_requested_change_sql"
 * )
 */
class IndicatorRequestedChangeSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('indicator_requested_changes', 'irc')
      ->fields('irc', ['id', 'changes', 'deadline', 'requested_at', 'answered_at', 'state', 'indicator_previous_state']);

    $query->leftJoin('questionnaire_countries', 'qc', 'irc.questionnaire_country_id = qc.id');
    $query->leftJoin('questionnaires', 'qs', 'qc.questionnaire_id = qs.id');
    $query->leftJoin('countries', 'cs', 'qc.country_id = cs.id');
    $query->leftJoin('indicators', 'is', 'irc.indicator_id = is.id');
    $query->leftJoin('users', 'usrb', 'irc.requested_by = usrb.id');
    $query->leftJoin('users', 'usrt', 'irc.requested_to = usrt.id');
    $query->leftJoin('users', 'usipa', 'irc.indicator_previous_assignee = usipa.id');

    // Aliasing fields.
    $query->addField('qs', 'title', 'questionnaire_name');
    $query->addField('cs', 'name', 'country_name');
    $query->addField('is', 'name', 'indicator_name');
    $query->addField('is', 'identifier', 'indicator_identifier');
    $query->addField('is', 'year', 'indicator_year');
    $query->addField('usrb', 'email', 'requested_by_email');
    $query->addField('usrt', 'name', 'requested_to_name');
    $query->addField('usrt', 'email', 'requested_to_email');
    $query->addField('usipa', 'email', 'indicator_previous_assignee_email');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'changes' => $this->t('Indicator requested changes'),
      'deadline' => $this->t('Indicator requested changes deadline'),
      'requested_at' => $this->t('Indicator requested changes requested at'),
      'answered_at' => $this->t('Indicator requested changes answered at'),
      'state' => $this->t('Indicator requested changes state'),
      'indicator_previous_state' => $this->t('Indicator previous state'),
      'questionnaire_name' => $this->t('Questionnaire name'),
      'country_name' => $this->t('Country name'),
      'indicator_name' => $this->t('Indicator name'),
      'indicator_identifier' => $this->t('Indicator identifier'),
      'indicator_year' => $this->t('Indicator year'),
      'requested_by_email' => $this->t('Requested by email'),
      'requested_to_name' => $this->t('Requested to name'),
      'requested_to_email' => $this->t('Requested to email'),
      'indicator_previous_assignee_email' => $this->t('Indicator previous assignee email'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'irc',
      ],
    ];
  }

}
