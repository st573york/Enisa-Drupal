<?php

namespace Drupal\user_management\Helper;

use Drupal\audit_management\Helper\AuditHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class User Helper.
 */
class UserHelper {

  /**
   * Function validateInputsForEdit.
   */
  public static function validateInputsForEdit($inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'country' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function getUserData.
   */
  public static function getUserData($entityTypeManager, $dateFormatter, $user) {
    if (!$user) {
      return [];
    }

    $last_login_at = $user->getLastLoginTime();

    $user_data = [
      'id' => $user->id(),
      'username' => $user->get('name')->value,
      'name' => $user->field_full_name->value ?? '',
      'email' => $user->getEmail(),
      'description' => $user->field_description->value ?? '',
      'role' => '',
      'country' => '',
      'phone' => $user->field_phone->value ?? '',
      'status' => ($user->field_blocked->value) ? 'Blocked' : 'Enabled',
      'last_login_at' => ($last_login_at) ? $dateFormatter->format($last_login_at, 'custom', 'Y-m-d H:i:s') : '',
      'is_active' => $user->isActive(),
    ];

    $roles = array_diff($user->getRoles(), ['authenticated']);
    $role = $entityTypeManager->getStorage('user_role')->load(reset($roles));
    if ($role) {
      $user_data['role'] = [
        'id' => $role->id(),
        'weight' => $role->getWeight(),
        'name' => UserPermissionsHelper::getRoleForDisplay($role->label()),
      ];
    }

    if ($user->hasField('field_country') &&
          !$user->get('field_country')->isEmpty()) {
      $country = $user->get('field_country')->entity;

      if (!is_null($country)) {
        $user_data['country'] = [
          'id' => $country->id(),
          'name' => $country->getName(),
        ];
      }
    }

    return $user_data;
  }

  /**
   * Function getUserEntities.
   */
  public static function getUserEntities($entityTypeManager, $currentUser, $dateFormatter) {
    $query = $entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_country', UserPermissionsHelper::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id'), 'IN')
      ->condition('status', 1);

    $or_condition = $query->orConditionGroup()
      ->condition('roles', UserPermissionsHelper::getUserRoles($entityTypeManager, $currentUser, 'id'), 'IN')
      ->condition('uid', $currentUser->id());

    $query->condition($or_condition);

    return $query->execute();
  }

  /**
   * Function emailUser.
   */
  public static function emailUser($services, $data) {
    $data['params']['#base_url'] = $services['requestStack']->getCurrentRequest()->getSchemeAndHttpHost();

    $body = $services['renderer']->render($data['params']);

    $params = [
      'subject' => $data['subject'],
      'message' => $body,
    ];

    $to = $data['to']->getEmail();
    $langcode = $data['to']->getPreferredLangcode();

    $services['mailManager']->mail('user_management', $data['theme'], $to, $langcode, $params, NULL, TRUE);
  }

  /**
   * Function deleteUser.
   */
  public static function deleteUser($services, $user, $user_data) {
    $user->set('status', 0);
    $user->save();

    AuditHelper::setCustomEvent(
      $services,
      [
        'action' => 'deleted',
        'entity' => $user_data['name'],
        'description' => 'The user has been deleted',
      ],
    );
  }

  /**
   * Function updateUserDetails.
   */
  public static function updateUserDetails($entityTypeManager, $currentUser, $data) {
    $user = $entityTypeManager->getStorage('user')->load($currentUser->id());

    $user->set('field_country', $data['country']);
    $user->save();
  }

}
