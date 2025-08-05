<?php

namespace Drupal\data_collection_management\Controller;

use Drupal\index_management\Helper\IndexHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Class Data Collection Twig Controller.
 */
final class DataCollectionTwigController extends ControllerBase {
  const ERROR_NOT_AUTHORIZED = 'You are not authorized for this action!';
  const ERROR_NOT_ALLOWED = 'The requested action is not allowed!';

  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable currentUser.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * Member variable requestStack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('twig')
    );
  }

  /**
   * Function showDataCollectionImportData.
   */
  public function showDataCollectionImportData() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    if (!UserPermissionsHelper::isAdmin($this->entityTypeManager, $this->currentUser)) {
      return new JsonResponse(['error' => self::ERROR_NOT_AUTHORIZED], 403);
    }

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);
    $latest_index_entity = IndexHelper::getLatestPublishedIndexEntity($this->entityTypeManager);
    if ($published_index_entity != $latest_index_entity) {
      return new JsonResponse(['error' => self::ERROR_NOT_ALLOWED], 405);
    }

    return new JsonResponse($this->twig->render('@enisa/ajax/data-collection-import.html.twig'));
  }

}
