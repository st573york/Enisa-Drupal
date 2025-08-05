<?php

namespace Drupal\user_management\Helper;

use Drupal\audit_management\Helper\AuditHelper;

/**
 * Class User Permissions Action Helper.
 */
class UserPermissionsActionHelper {

  /**
   * Function updateCountryPrimaryPocToPoc.
   */
  private static function updateCountryPrimaryPocToPoc($entityTypeManager, $country) {
    $query = $entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_country', $country->id())
      ->condition('roles', 'primary_poc');

    $entities = $query->execute();

    if (!empty($entities)) {
      $user = $entityTypeManager->getStorage('user')->load(reset($entities));

      $user->set('roles', ['authenticated', 'poc']);
      $user->save();
    }
  }

  /**
   * Function updateUserPermissions.
   */
  public static function updateUserPermissions($services, $user, $user_data, $country, $role) {
    $replicate_user = $user->createDuplicate();

    if ($role->id() == 'primary_poc') {
      self::updateCountryPrimaryPocToPoc($services['entityTypeManager'], $country);
    }

    $user->set('field_country', $country->id());
    $user->set('roles', ['authenticated', $role->id()]);
    $user->save();

    $changes = AuditHelper::getChangesOnUpdate($replicate_user, $user);

    if (!empty($changes)) {
      AuditHelper::transformUser($services, $changes);
      AuditHelper::setCustomEvent(
        $services,
        [
          'action' => 'updated',
          'entity' => $user_data['name'],
          'description' => 'The user has been updated',
          'old_values' => json_encode($changes['old_values']),
          'new_values' => json_encode($changes['new_values']),
        ],
      );
    }
  }

  /**
   * Function updateUserStatus.
   */
  public static function updateUserStatus($services, $user, $inputs = []) {
    $replicate_user = $user->createDuplicate();

    $dbBlocked = (int) $user->field_blocked->value;

    $user->set('field_blocked', $inputs['blocked'] ?? !$dbBlocked);
    $user->save();

    if ((isset($inputs['blocked']) && $dbBlocked != $inputs['blocked']) ||
        !isset($inputs['blocked'])) {
      $usersNotified = [];

      $user_data = UserHelper::getUserData($services['entityTypeManager'], $services['dateFormatter'], $user);

      $changes = AuditHelper::getChangesOnUpdate($replicate_user, $user);

      if (!empty($changes)) {
        AuditHelper::transformUser($services, $changes);
        AuditHelper::setCustomEvent(
          $services,
          [
            'action' => 'updated',
            'entity' => $user_data['name'],
            'description' => 'The user has been ' . mb_strtolower($user_data['status']),
            'old_values' => json_encode($changes['old_values']),
            'new_values' => json_encode($changes['new_values']),
          ],
        );
      }

      $user_entities = UserPermissionsHelper::getUserEntitiesByCountryAndRole(
        $services['entityTypeManager'],
        [
          'roles' => UserPermissionsHelper::getRolesBetweenWeights($services['entityTypeManager'], 'id', 5, 5),
        ]
      );

      if (!UserPermissionsHelper::isAdmin($services['entityTypeManager'], $user)) {
        $user_entities += UserPermissionsHelper::getUserEntitiesByCountryAndRole(
          $services['entityTypeManager'],
          [
            'countries' => [$user_data['country']['id']],
            'roles' => UserPermissionsHelper::getRolesBetweenWeights($services['entityTypeManager'], 'id', 6, 6),
          ]
        );
      }

      $current_user = $services['entityTypeManager']->getStorage('user')->load($services['currentUser']->id());
      $current_user_data = UserHelper::getUserData($services['entityTypeManager'], $services['dateFormatter'], $current_user);

      $notifyData = [
        'subject' => 'EU-CSI User Access',
        'theme' => 'user_access',
        'params' => [
          '#theme' => 'user_access',
          '#user' => 'User access for <strong>' . $user_data['email'] . '</strong>',
          '#status' => ($user_data['status'] == 'Blocked') ? '<span style="color: #D2224D;">blocked</span>' : 'enabled',
          '#author_name' => $current_user_data['name'],
          '#author_email' => $current_user_data['email'],
          '#url' => 'http://localhost',
        ],
      ];

      $notifyUsers = $services['entityTypeManager']->getStorage('user')->loadMultiple($user_entities);

      // Notify all country PoCs and enisa admins.
      foreach ($notifyUsers as $notifyUser) {
        if (!in_array($notifyUser->id(), $usersNotified)) {
          $notifyData['to'] = $notifyUser;
          $notifyData['params']['#user'] = 'User access for <strong>' . $user_data['email'] . '</strong>';

          UserHelper::emailUser($services, $notifyData);

          array_push($usersNotified, $notifyUser->id());
        }
      }

      // Notify user.
      if (!in_array($user->id(), $usersNotified)) {
        $notifyData['to'] = $user;
        $notifyData['params']['#user'] = 'Your access';

        UserHelper::emailUser($services, $notifyData);
      }
    }
  }

}
