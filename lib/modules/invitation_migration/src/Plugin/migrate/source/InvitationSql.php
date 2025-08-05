<?php

namespace Drupal\invitation_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "invitation_sql"
 * )
 */
class InvitationSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('invitations', 'inv')
      ->fields('inv', ['id', 'name', 'email', 'invited_at', 'registered_at', 'expired_at', 'status_id', 'hash'])
      ->fields('us', ['email']);

    $query->leftJoin('countries', 'co', 'inv.country_id = co.id');
    $query->leftJoin('roles', 'ro', 'inv.role_id = ro.id');
    $query->leftJoin('users', 'us', 'inv.invited_by = us.id');

    // Aliasing fields.
    $query->addField('co', 'name', 'country_name');
    $query->addField('ro', 'name', 'role_name');
    $query->addField('us', 'email', 'inviter_email');

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
      'country_name' => $this->t('User country name'),
      'role_name' => $this->t('User role name'),
      'inviter_email' => $this->t('Inviter email'),
      'invited_at' => $this->t('User invited at'),
      'registered_at' => $this->t('User registered at'),
      'expired_at' => $this->t('Invitation expired at'),
      'status_id' => $this->t('Invitation status'),
      'hash' => $this->t('Invitation hash'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'inv',
      ],
    ];
  }

}
