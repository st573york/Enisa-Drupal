<?php

namespace Drupal\audit_management\Helper;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;

/**
 * Class Audit Helper.
 */
class AuditHelper {

  /**
   * Function getChangesOnCreate.
   */
  public static function getChangesOnCreate($entity) {
    $changes = [];

    foreach (array_keys($entity->getFieldDefinitions()) as $field_name) {
      if (!preg_match('/^field_/', $field_name) &&
          $field_name !== 'title' &&
          $field_name !== 'status') {
        continue;
      }

      if (!$entity->hasField($field_name)) {
        continue;
      }

      $value = ($entity->get($field_name)->entity) ? $entity->get($field_name)->target_id : $entity->get($field_name)->value;
      $field_name = preg_replace('/^field_/', '', $field_name);

      $changes['new_values'][$field_name] = $value;
    }

    return $changes;
  }

  /**
   * Function getChangesOnUpdate.
   */
  public static function getChangesOnUpdate($entity1, $entity2) {
    $changes = [];

    if ($entity1->bundle() !== $entity2->bundle()) {
      return $changes;
    }

    foreach (array_keys($entity1->getFieldDefinitions()) as $field_name) {
      if (!preg_match('/^field_/', $field_name) &&
          $field_name !== 'title' &&
          $field_name !== 'status' &&
          $field_name !== 'roles') {
        continue;
      }

      if (!$entity1->hasField($field_name) || !$entity2->hasField($field_name)) {
        continue;
      }

      $value1 = ($entity1->get($field_name)->entity) ? $entity1->get($field_name)->target_id : $entity1->get($field_name)->value;
      $value2 = ($entity2->get($field_name)->entity) ? $entity2->get($field_name)->target_id : $entity2->get($field_name)->value;

      if ($value1 !== $value2) {
        $field_name = preg_replace('/^field_/', '', $field_name);

        $changes['old_values'][$field_name] = $value1;
        $changes['new_values'][$field_name] = $value2;
      }
    }

    return $changes;
  }

  /**
   * Function getActions.
   */
  public static function getActions($database) {
    return $database->select('audits', 'as')
      ->fields('as', ['action'])
      ->distinct()
      ->execute()
      ->fetchCol();
  }

  /**
   * Function getTotalRecords.
   */
  public static function getTotalRecords($database) {
    return $database->select('audits', 'as')
      ->fields('as')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Function getFilteredRecords.
   */
  public static function getFilteredRecords($database, $inputs) {
    $minDate = $inputs['minDate'];
    $maxDate = $inputs['maxDate'];
    $action = $inputs['action'];
    $search = $inputs['search']['value'];

    $query = $database->select('audits', 'as')
      ->fields('as')
      ->fields('usffn', ['field_full_name_value'])
      ->condition('as.timestamp', strtotime($minDate), '>=')
      ->condition('as.timestamp', strtotime($maxDate . ' 23:59:59'), '<=');

    if ($action != 'All') {
      $query->condition('as.action', $action);
    }

    if (!empty($search)) {
      $or = $query->orConditionGroup()
        ->condition('as.ip_address', '%' . $search . '%', 'LIKE')
        ->condition('as.description', '%' . $search . '%', 'LIKE')
        ->condition('as.affected_entity', '%' . $search . '%', 'LIKE')
        ->condition('usffn.field_full_name_value', '%' . $search . '%', 'LIKE');

      $regex_expr = "REGEXP_REPLACE(as.new_values, '\"json_data.*\\\\}', '')";
      $or->where("$regex_expr LIKE :regex_search", [':regex_search' => '%' . $search . '%']);

      $query->condition($or);
    }

    $query->addExpression("DATE_FORMAT(FROM_UNIXTIME(as.timestamp), '%Y-%m-%d %T')", 'date');
    $query->leftJoin('user__field_full_name', 'usffn', 'as.user_id = usffn.entity_id');

    // Aliasing fields.
    $query->addField('usffn', 'field_full_name_value', 'user');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Function transformIndex.
   */
  public static function transformIndex(&$changes) {
    if (isset($changes['new_values']['status'])) {
      $changes['new_values']['status'] = ($changes['new_values']['status']) ? 'Published' : 'Draft';
    }

    if (isset($changes['new_values']['eu_published'])) {
      $changes['new_values']['eu_reports_visualisations'] = ($changes['new_values']['eu_published']) ? 'Published' : 'Unpublished';
    }

    if (isset($changes['new_values']['ms_published'])) {
      $changes['new_values']['ms_reports_visualisations'] = ($changes['new_values']['ms_published']) ? 'Published' : 'Unpublished';
    }

    unset(
      $changes['new_values']['collection_started'],
      $changes['new_values']['collection_deadline'],
      $changes['new_values']['eu_published'],
      $changes['new_values']['ms_published'],
      $changes['new_values']['last_action'],
      $changes['new_values']['last_action_date'],
      $changes['new_values']['last_action_state'],
    );
  }

  /**
   * Function transformSurvey.
   */
  public static function transformSurvey($services, &$changes) {
    if (isset($changes['new_values']['index'])) {
      $index = $services['entityTypeManager']->getStorage('node')->load($changes['new_values']['index']);
      $index_data = IndexHelper::getIndexData($services['dateFormatter'], $index);

      $changes['new_values']['index'] = $index_data['title'];
    }

    unset(
      $changes['new_values']['assigned_indicators'],
      $changes['new_values']['assigned_users'],
      $changes['new_values']['description'],
      $changes['new_values']['published'],
      $changes['new_values']['status'],
      $changes['new_values']['year'],
    );
  }

  /**
   * Function transformUser.
   */
  public static function transformUser($services, &$changes) {
    if (isset($changes['new_values']['country'])) {
      $country = GeneralHelper::getTaxonomyTerm($services['entityTypeManager'], 'countries', $changes['new_values']['country'], 'tid');

      $changes['new_values']['country'] = $country->getName();
    }

    if (isset($changes['new_values']['roles'])) {
      $changes['new_values']['role'] = UserPermissionsHelper::getRoleForDisplay($changes['new_values']['roles']);
    }

    if (isset($changes['new_values']['blocked'])) {
      $changes['new_values']['status'] = ($changes['new_values']['blocked']) ? 'Blocked' : 'Enabled';
    }

    unset(
      $changes['new_values']['roles'],
      $changes['new_values']['blocked'],
    );
  }

  /**
   * Function transformInvitation.
   */
  public static function transformInvitation($services, &$changes) {
    $changes['new_values']['name'] = $changes['new_values']['title'];

    if (isset($changes['new_values']['country'])) {
      $country = GeneralHelper::getTaxonomyTerm($services['entityTypeManager'], 'countries', $changes['new_values']['country'], 'tid');

      $changes['new_values']['country'] = $country->getName();
    }

    if (isset($changes['new_values']['role'])) {
      $changes['new_values']['role'] = UserPermissionsHelper::getRoleForDisplay($changes['new_values']['role']);
    }

    unset(
      $changes['new_values']['title'],
      $changes['new_values']['expired_date'],
      $changes['new_values']['hash'],
      $changes['new_values']['invited_date'],
      $changes['new_values']['inviter_user'],
      $changes['new_values']['registered_date'],
      $changes['new_values']['invitation_state'],
      $changes['new_values']['status'],
    );
  }

  /**
   * Function setCustomEvent.
   */
  public static function setCustomEvent($services, $inputs) {
    // Get the current request to extract the IP address and user agent.
    $request = $services['requestStack']->getCurrentRequest();

    $services['database']->insert('audits')->fields([
      'user_id' => $services['currentUser']->id(),
      'ip_address' => $request->getClientIp(),
      'user_agent' => $request->headers->get('User-Agent'),
      'action' => $inputs['action'],
      'affected_entity' => $inputs['entity'] ?? NULL,
      'description' => $inputs['description'] ?? NULL,
      'old_values' => $inputs['old_values'] ?? NULL,
      'new_values' => $inputs['new_values'] ?? NULL,
      'timestamp' => strtotime($services['dateFormatter']->format($services['time']->getCurrentTime(), 'custom', 'd-m-Y H:i:s')),
    ])->execute();
  }

}
