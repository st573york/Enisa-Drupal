<?php

namespace Drupal\survey_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "survey_sql"
 * )
 */
class SurveySql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('questionnaires', 'qs')
      ->fields('qs', ['id', 'title', 'description', 'year', 'deadline', 'published', 'published_at'])
      ->fields('us', ['email']);

    $query->leftJoin('users', 'us', 'qs.user_id = us.id');
    $query->leftJoin('index_configurations', 'ic', 'qs.index_configuration_id = ic.id');

    // Aliasing fields.
    $query->addField('ic', 'name', 'index_name');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'title' => $this->t('Survey title'),
      'description' => $this->t('Survey description'),
      'year' => $this->t('Survey year'),
      'deadline' => $this->t('Survey deadline'),
      'published' => $this->t('Survey published'),
      'published_at' => $this->t('Survey published at'),
      'email' => $this->t('User email'),
      'index_name' => $this->t('User index'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'qs',
      ],
    ];
  }

}
