<?php

namespace Drupal\user_management\Helper;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Class User Permissions Helper.
 */
class UserPermissionsHelper {
  const ERROR_NOT_ALLOWED = 'User cannot be updated as the requested action is not allowed!';

  /**
   * Function validateInputsForEdit.
   */
  public static function validateInputsForEdit($inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'country' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'role' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'blocked' => new NotNull([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function canUpdateUserCountryRole.
   */
  public static function canUpdateUserCountryRole(
    $entityTypeManager,
    $currentUser,
    $dateFormatter,
    $user,
    $db_permission_country,
    $country,
    $db_permission_role,
    $role,
  ) {
    $availableCountries = GeneralHelper::getTaxonomyTerms($entityTypeManager, 'countries');
    $availableRoles = self::getRolesBetweenWeights($entityTypeManager, 'id', 5, 9);

    if (!in_array($country->id(), $availableCountries) ||
        !in_array($role->id(), $availableRoles)) {
      return [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];
    }

    if ($country->getName() != ConstantHelper::USER_GROUP && $role->id() == 'enisa_administrator' ||
        $country->getName() == ConstantHelper::USER_GROUP && in_array($role->id(), ['primary_poc', 'poc', 'operator'])) {
      return [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];
    }

    if (self::isPoC($entityTypeManager, $currentUser) && !in_array($db_permission_country['id'], self::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id')) ||
        self::isPoC($entityTypeManager, $currentUser) && !in_array($country->id(), self::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id')) ||
        self::isPoC($entityTypeManager, $currentUser) && !in_array($db_permission_role['id'], self::getUserRoles($entityTypeManager, $currentUser, 'id')) && $user->id() != $currentUser->id() ||
        self::isPoC($entityTypeManager, $currentUser) && !in_array($role->id(), self::getUserRoles($entityTypeManager, $currentUser, 'id')) && $user->id() != $currentUser->id() ||
        self::isPoC($entityTypeManager, $currentUser) && !in_array($db_permission_role['id'], self::getUserRoles($entityTypeManager, $currentUser, 'id', TRUE)) && $user->id() == $currentUser->id() ||
        self::isPoC($entityTypeManager, $currentUser) && !in_array($role->id(), self::getUserRoles($entityTypeManager, $currentUser, 'id', TRUE)) && $user->id() == $currentUser->id()) {
      return [
        'type' => 'error',
        'msg' => 'User cannot be updated as you are not authorized for this action!',
        'status' => 403,
      ];
    }

    return [
      'type' => 'success',
      'msg' => 'User can be successfully updated!',
    ];
  }

  /**
   * Function canUpdateUserStatus.
   */
  public static function canUpdateUserStatus(
    $entityTypeManager,
    $currentUser,
    $dateFormatter,
    $user,
    $inputs,
    $db_permission_country,
    $db_permission_role,
  ) {
    $db_blocked = (int) $user->field_blocked->value;

    $status_updated = (($inputs['toggle']) || (!$inputs['toggle'] && isset($inputs['blocked']) && $inputs['blocked'] != $db_blocked)) ? TRUE : FALSE;

    if ($status_updated) {
      if (self::isAdmin($entityTypeManager, $currentUser) && $currentUser->id() == $user->id()) {
        return [
          'type' => 'error',
          'msg' => self::ERROR_NOT_ALLOWED,
          'status' => 405,
        ];
      }

      if (self::isPoC($entityTypeManager, $currentUser) && $currentUser->id() == $user->id() ||
          self::isPoC($entityTypeManager, $currentUser) && (!in_array($db_permission_country['id'], self::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id'))) ||
          self::isPoC($entityTypeManager, $currentUser) && (!in_array($db_permission_role['id'], self::getUserRoles($entityTypeManager, $currentUser, 'id')))) {
        return [
          'type' => 'error',
          'msg' => 'User status cannot be updated as you are not authorized for this action!',
          'status' => 403,
        ];
      }
    }

    return [
      'type' => 'success',
      'msg' => 'User status can be successfully updated!',
    ];
  }

  /**
   * Function canDowngradePrimaryPoC.
   */
  public static function canDowngradePrimaryPoC($db_permission_country, $db_permission_role, $role) {
    if ($db_permission_role['id'] == 'primary_poc' &&
        $role->id() != 'primary_poc') {
      return [
        'type' => 'warning',
        'msg' => 'This user is the Primary PoC for ' . $db_permission_country['name'] . ' and cannot be updated. Please first assign the Primary PoC role to another user for ' . $db_permission_country['name'] . '!',
        'status' => 403,
      ];
    }

    return [
      'type' => 'success',
      'msg' => 'Primary PoC can be successfully downgraded!',
    ];
  }

  /**
   * Function canDeleteUser.
   */
  public static function canDeleteUser($entityTypeManager, $currentUser) {
    if (self::isAdmin($entityTypeManager, $currentUser)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Function getUserCountries.
   */
  public static function getUserCountries($entityTypeManager, $currentUser, $dateFormatter, $field) {
    $countries = [];

    $query = $entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'countries');

    if (!self::isAdmin($entityTypeManager, $currentUser)) {
      $logged_in_user = $entityTypeManager->getStorage('user')->load($currentUser->id());
      $logged_in_user_data = UserHelper::getUserData($entityTypeManager, $dateFormatter, $logged_in_user);

      $query->condition('name', $logged_in_user_data['country']['name']);
    }

    $entities = $query->execute();

    foreach ($entityTypeManager->getStorage('taxonomy_term')->loadMultiple($entities) as $country) {
      if ($field == 'id') {
        array_push($countries, $country->id());
      }
      elseif ($field == 'name') {
        array_push($countries, $country->getName());
      }
      elseif ($field == 'entity') {
        array_push($countries, $country);
      }
    }

    return $countries;
  }

  /**
   * Function getRoleForDisplay.
   */
  public static function getRoleForDisplay($name) {
    switch ($name) {
      case 'ENISA Administrator':
        return 'Admin';

      case 'primary_poc':
        return 'Primary PoC';

      case 'poc':
        return 'PoC';

      case 'operator':
        return 'Operator';

      case 'viewer':
        return 'Viewer';

      default:
        return $name;
    }
  }

  /**
   * Function getRolesBetweenWeights.
   */
  public static function getRolesBetweenWeights($entityTypeManager, $field, $min_weight, $max_weight) {
    $roles = [];

    foreach ($entityTypeManager->getStorage('user_role')->loadMultiple() as $role) {
      $weight = $role->getWeight();

      if ($weight >= $min_weight &&
          $weight <= $max_weight) {
        if ($field == 'id') {
          array_push($roles, $role->id());
        }
        elseif ($field == 'name') {
          array_push($roles, self::getRoleForDisplay($role->label()));
        }
        elseif ($field == 'entity') {
          $role->set('label', self::getRoleForDisplay($role->label()));

          array_push($roles, $role);
        }
      }
    }

    return $roles;
  }

  /**
   * Function getUserRoles.
   */
  public static function getUserRoles($entityTypeManager, $currentUser, $field, $yourself = FALSE) {
    if (self::isAdmin($entityTypeManager, $currentUser)) {
      return self::getRolesBetweenWeights($entityTypeManager, $field, 5, 9);
    }

    if (self::isPoC($entityTypeManager, $currentUser)) {
      if (self::isPrimaryPoC($entityTypeManager, $currentUser)) {
        if ($yourself) {
          return self::getRolesBetweenWeights($entityTypeManager, $field, 6, 9);
        }

        return self::getRolesBetweenWeights($entityTypeManager, $field, 7, 9);
      }
      else {
        if ($yourself) {
          return self::getRolesBetweenWeights($entityTypeManager, $field, 7, 9);
        }

        return self::getRolesBetweenWeights($entityTypeManager, $field, 8, 9);
      }
    }

    return [];
  }

  /**
   * Function getUserEntitiesByCountryAndRole.
   */
  public static function getUserEntitiesByCountryAndRole($entityTypeManager, $data) {
    $query = $entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE);

    if (isset($data['countries'])) {
      $query->condition('field_country', $data['countries'], 'IN');
    }

    if (isset($data['roles'])) {
      $query->condition('roles', $data['roles'], 'IN');
    }

    return $query->execute();
  }

  /**
   * Function userHasRole.
   */
  public static function userHasRole($entityTypeManager, $currentUser, $roles) {
    $logged_in_user = $entityTypeManager->getStorage('user')->load($currentUser->id());

    foreach ($roles as $role) {
      if ($logged_in_user->hasRole($role)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Function isEnisa.
   */
  public static function isEnisa($entityTypeManager, $currentUser, $dateFormatter) {
    $logged_in_user = $entityTypeManager->getStorage('user')->load($currentUser->id());
    $logged_in_user_data = UserHelper::getUserData($entityTypeManager, $dateFormatter, $logged_in_user);

    return (isset($logged_in_user_data['country']['name']) && $logged_in_user_data['country']['name'] == 'ENISA') ? TRUE : FALSE;
  }

  /**
   * Function isAdmin.
   */
  public static function isAdmin($entityTypeManager, $currentUser) {
    return self::userHasRole($entityTypeManager, $currentUser, ['administrator', 'enisa_administrator']);
  }

  /**
   * Function isPrimaryPoC.
   */
  public static function isPrimaryPoC($entityTypeManager, $currentUser) {
    return self::userHasRole($entityTypeManager, $currentUser, ['primary_poc']);
  }

  /**
   * Function isPoC.
   */
  public static function isPoC($entityTypeManager, $currentUser) {
    return self::userHasRole($entityTypeManager, $currentUser, ['primary_poc', 'poc']);
  }

  /**
   * Function isOperator.
   */
  public static function isOperator($entityTypeManager, $currentUser) {
    return self::userHasRole($entityTypeManager, $currentUser, ['operator']);
  }

  /**
   * Function isViewer.
   */
  public static function isViewer($entityTypeManager, $currentUser) {
    return self::userHasRole($entityTypeManager, $currentUser, ['viewer']);
  }

}
