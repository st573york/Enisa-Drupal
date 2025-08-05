<?php

namespace Drupal\user_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "user_sql"
 * )
 */
class UserSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('users', 'us')
      ->fields('us', [
        'id',
        'name',
        'email',
        'password',
        'description',
        'phone',
        'blocked',
        'inactive_notified',
        'inactive_deadline',
      ])
      ->fields('pm', ['country_id', 'role_id'])
      ->condition('us.deleted_at', NULL, 'IS');

    $query->leftJoin('permissions', 'pm', 'us.id = pm.user_id');
    $query->leftJoin('roles', 'ro', 'pm.role_id = ro.id');
    $query->leftJoin('countries', 'co', 'pm.country_id = co.id');

    // Aliasing fields.
    $query->addField('ro', 'name', 'role_name');
    $query->addField('co', 'name', 'country_name');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('User name'),
      'email' => $this->t('User email'),
      'password' => $this->t('User password'),
      'description' => $this->t('User description'),
      'role_name' => $this->t('User role'),
      'country_name' => $this->t('User country'),
      'phone' => $this->t('User phone'),
      'blocked' => $this->t('User blocked'),
      'inactive_notified' => $this->t('User inactive notified'),
      'inactive_deadline' => $this->t('User inactive deadline'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'us',
      ],
    ];
  }

}
