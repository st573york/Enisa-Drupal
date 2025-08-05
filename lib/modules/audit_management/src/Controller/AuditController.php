<?php

namespace Drupal\audit_management\Controller;

use Drupal\audit_management\Helper\AuditHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class Audit Controller.
 */
final class AuditController extends ControllerBase {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * Member variable dateFormatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;
  /**
   * Member variable time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Function viewAudit.
   */
  public function viewAudit() {
    $actions = AuditHelper::getActions($this->database);
    sort($actions);

    $actions = array_map(function ($action) {
      return Unicode::ucfirst(mb_strtolower($action));
    }, $actions);

    return [
      '#theme' => 'audit',
      // Pass data to Twig templates.
      '#dateToday' => $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'd-m-Y'),
      '#actions' => $actions,
    ];
  }

  /**
   * Function listAudit.
   */
  public function listAudit(Request $request) {
    $inputs = $request->query->all();

    $totalRecords = AuditHelper::getTotalRecords($this->database);
    $filteredRecords = AuditHelper::getFilteredRecords($this->database, $inputs);

    /* Perform offset, limit and order by to avoid running the same query 3 times (recordsTotal, recordsFiltered, data). */
    $column = $inputs['order'][0]['column'];
    $order = $inputs['columns'][$column]['data'];
    $sort = ($inputs['order'][0]['dir'] == 'asc') ? SORT_ASC : SORT_DESC;
    $offset = $inputs['start'];
    $length = $inputs['length'];

    $keys = array_column($filteredRecords, $order);
    array_multisort($keys, $sort, $filteredRecords);

    $data = array_slice($filteredRecords, $offset, $length);

    return new JsonResponse([
      'status' => 'success',
      'draw' => (int) $inputs['draw'],
      'recordsTotal' => count($totalRecords),
      'recordsFiltered' => count($filteredRecords),
      'data' => $data,
    ]);
  }

}
