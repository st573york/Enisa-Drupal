<?php

namespace Drupal\index_and_survey_configuration_management\Controller;

use Drupal\index_and_survey_configuration_management\Helper\AreaHelper;
use Drupal\index_management\Helper\IndexHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Environment;

/**
 * Class Area Controller.
 */
final class AreaController extends ControllerBase {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, Environment $twig) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('twig')
    );
  }

  /**
   * Function listAreas.
   */
  public function listAreas() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $entities = AreaHelper::getAreaEntities($this->entityTypeManager, $year);
    $data = AreaHelper::getAreasData($this->entityTypeManager, $entities);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function createOrShowArea.
   */
  public function createOrShowArea($action = 'create', $data = NULL) {
    return new JsonResponse($this->twig->render('@enisa/ajax/area-management.html.twig', [
      'action' => $action,
      'selected_area' => $data,
    ]));
  }

  /**
   * Function showArea.
   */
  public function showArea($area_id) {
    $area = $this->entityTypeManager->getStorage('node')->load($area_id);
    $area_data = AreaHelper::getAreaData($area);

    return $this->createOrShowArea('show', $area_data);
  }

  /**
   * Function storeArea.
   */
  public function storeArea(Request $request) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['year'] = $year;

    $errors = AreaHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    AreaHelper::storeArea($this->entityTypeManager, $inputs);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function updateArea.
   */
  public function updateArea(Request $request, $area_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['id'] = $area_id;
    $inputs['year'] = $year;

    $errors = AreaHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $area = $this->entityTypeManager->getStorage('node')->load($area_id);

    AreaHelper::updateArea($area, $inputs);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function deleteArea.
   */
  public function deleteArea($area_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $area = $this->entityTypeManager->getStorage('node')->load($area_id);

    AreaHelper::deleteArea($area);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

}
