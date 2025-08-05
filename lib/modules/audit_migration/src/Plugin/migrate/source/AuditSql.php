<?php

namespace Drupal\audit_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "audit_sql"
 * )
 */
class AuditSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('audits', 'aus')
      ->fields('aus', [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'event',
        'description',
        'auditable_name',
        'old_values',
        'new_values',
        'created_at',
      ])
      ->fields('us', ['email'])
      ->condition('us.deleted_at', NULL, 'IS');

    $query->leftJoin('users', 'us', 'aus.user_id = us.id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'user_id' => $this->t('Audit user'),
      'ip_address' => $this->t('Audit user ip address'),
      'user_agent' => $this->t('Audit user agent'),
      'event' => $this->t('Audit event'),
      'description' => $this->t('Audit description'),
      'auditable_name' => $this->t('Affected entity'),
      'old_values' => $this->t('Audit old values'),
      'new_values' => $this->t('Audit new values'),
      'created_at' => $this->t('Audit timestamp'),
      'email' => $this->t('Audit user email'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'aus',
      ],
    ];
  }

}
