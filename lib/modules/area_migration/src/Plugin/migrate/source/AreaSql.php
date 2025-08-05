<?php

namespace Drupal\area_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "area_sql"
 * )
 */
class AreaSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('areas', 'ar')
      ->fields('ar', ['id', 'name', 'description', 'default_weight', 'identifier', 'year', 'clone_year']);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('Area name'),
      'description' => $this->t('Area description'),
      'default_weight' => $this->t('Area default weight'),
      'identifier' => $this->t('Area identifier'),
      'year' => $this->t('Area year'),
      'clone_year' => $this->t('Area clone year'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'ar',
      ],
    ];
  }

}
