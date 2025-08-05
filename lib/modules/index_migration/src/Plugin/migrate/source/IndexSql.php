<?php

namespace Drupal\index_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "index_sql"
 * )
 */
class IndexSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('index_configurations', 'ic')
      ->fields('ic', [
        'id',
        'name',
        'description',
        'year',
        'draft',
        'json_data',
        'eu_published',
        'ms_published',
        'collection_started',
        'collection_deadline',
      ])
      ->fields('us', ['email'])
      ->condition('ic.deleted_at', NULL, 'IS');

    $query->leftJoin('users', 'us', 'ic.user_id = us.id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('Index name'),
      'description' => $this->t('Index description'),
      'year' => $this->t('Index year'),
      'draft' => $this->t('Index status'),
      'json_data' => $this->t('Index json data'),
      'eu_published' => $this->t('Index EU published'),
      'ms_published' => $this->t('Index MS published'),
      'collection_started' => $this->t('Index collection started'),
      'collection_deadline' => $this->t('Index collection deadline'),
      'email' => $this->t('User email'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'ic',
      ],
    ];
  }

}
