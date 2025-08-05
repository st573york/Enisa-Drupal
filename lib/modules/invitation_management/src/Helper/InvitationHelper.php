<?php

namespace Drupal\invitation_management\Helper;

use Drupal\audit_management\Helper\AuditHelper;
use Drupal\general_management\Helper\ConstantHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class Invitation Helper.
 */
class InvitationHelper {
  const ERROR_NOT_ALLOWED = 'User cannot be invited as the requested action is not allowed!';

  /**
   * Function validateInputsForCreate.
   */
  public static function validateInputsForCreate($entityTypeManager, $inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'firstname' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'lastname' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'email' => [
        new NotBlank(['message' => ValidationHelper::$requiredMessage]),
        new Email(['message' => 'The email must be a valid email address.']),
      ],
      'country' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'role' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    if (!self::isEmailUnique($entityTypeManager, $inputs)) {
      $errors->add(new ConstraintViolation(
            'The email is already registered.',
            '',
            [],
            '',
            'email',
            $inputs['email']
        ));
    }

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function isEmailUnique.
   */
  public static function isEmailUnique($entityTypeManager, $inputs) {
    $query = $entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('mail', $inputs['email'])
      ->condition('status', 1);

    $results = $query->execute();

    if (!empty($results)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function canInviteUser.
   */
  public static function canInviteUser($entityTypeManager, $currentUser, $dateFormatter, $inputs) {
    $country = $entityTypeManager->getStorage('taxonomy_term')->load($inputs['country']);
    $role = $entityTypeManager->getStorage('user_role')->load($inputs['role']);

    $availableCountries = GeneralHelper::getTaxonomyTerms($entityTypeManager, 'countries');
    $availableRoles = UserPermissionsHelper::getRolesBetweenWeights($entityTypeManager, 'id', 5, 9);

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

    if (UserPermissionsHelper::isPrimaryPoC($entityTypeManager, $currentUser) && !in_array($country->id(), UserPermissionsHelper::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id')) ||
          UserPermissionsHelper::isPrimaryPoC($entityTypeManager, $currentUser) && !in_array($role->id(), UserPermissionsHelper::getUserRoles($entityTypeManager, $currentUser, 'id'))) {
      return [
        'type' => 'error',
        'msg' => 'User cannot be invited as you are not authorized for this action!',
        'status' => 403,
      ];
    }

    return [
      'type' => 'success',
      'msg' => 'User can be successfully invited!',
    ];
  }

  /**
   * Function getInvitationDataReferenceEntities.
   */
  public static function getInvitationDataReferenceEntities($node, &$node_data) {
    if ($node->hasField('field_role') &&
          !$node->get('field_role')->isEmpty()) {
      $role = $node->get('field_role')->entity;

      if (!is_null($role)) {
        $node_data['role'] = [
          'id' => $role->id(),
          'name' => $role->label(),
        ];
      }
    }

    if ($node->hasField('field_country') &&
          !$node->get('field_country')->isEmpty()) {
      $country = $node->get('field_country')->entity;

      if (!is_null($country)) {
        $node_data['country'] = [
          'id' => $country->id(),
          'name' => $country->getName(),
        ];
      }
    }

    if ($node->hasField('field_invitation_state') &&
          !$node->get('field_invitation_state')->isEmpty()) {
      $state = $node->get('field_invitation_state')->entity;

      if (!is_null($state)) {
        $node_data['state'] = [
          'id' => (int) $state->field_state_id->value ?? '',
          'name' => $state->getName(),
        ];
      }
    }
  }

  /**
   * Function getInvitationData.
   */
  public static function getInvitationData($dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $author = $node->getOwner();

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'email' => $node->field_email->value ?? '',
      'role' => '',
      'country' => '',
      'inviter_user' => $author->field_full_name->value ?? '',
      'invited_date' => ($node->field_invited_date->value) ? $dateFormatter->format($node->field_invited_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'registered_date' => ($node->field_registered_date->value) ? $dateFormatter->format($node->field_registered_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'expired_date' => ($node->field_expired_date->value) ? $dateFormatter->format($node->field_expired_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'state' => '',
      'status' => ($node->status->value) ? 'Published' : 'Draft',
    ];

    self::getInvitationDataReferenceEntities($node, $node_data);

    return $node_data;
  }

  /**
   * Function getInvitationEntities.
   */
  public static function getInvitationEntities($entityTypeManager, $currentUser, $dateFormatter) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'invitation')
      ->condition('field_country', UserPermissionsHelper::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id'), 'IN');

    return $query->execute();
  }

  /**
   * Function storeInvitation.
   */
  public static function storeInvitation($services, $inputs) {
    $term = GeneralHelper::getTaxonomyTerm($services['entityTypeManager'], 'invitation_states', 'Pending');

    $data = [
      'type' => 'invitation',
      'title' => $inputs['name'],
      'field_email' => $inputs['email'],
      'field_country' => $inputs['country'],
      'field_role' => $inputs['role'],
      'field_inviter_user' => $services['currentUser']->id(),
      'field_invited_date' => $services['time']->getCurrentTime(),
      'field_expired_date' => strtotime('now + ' . Settings::get('registration_deadline') . ' hours'),
      'field_invitation_state' => $term->id(),
      'hash' => hash('sha256', Crypt::randomBytesBase64(16) . $inputs['email']),
    ];

    $invitation = $services['entityTypeManager']->getStorage('node')->create($data);
    $invitation->save();

    $changes = AuditHelper::getChangesOnCreate($invitation);

    AuditHelper::transformInvitation($services, $changes);
    AuditHelper::setCustomEvent(
      $services,
      [
        'action' => 'created',
        'description' => 'The user has sent an invitation',
        'new_values' => json_encode($changes['new_values']),
      ],
    );
  }

}
