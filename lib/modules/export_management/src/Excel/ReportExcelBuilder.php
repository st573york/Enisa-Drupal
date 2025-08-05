<?php

namespace Drupal\export_management\Excel;

use Drupal\export_management\Helper\ExportHelper;
use PhpOffice\PhpSpreadsheet\Reader\Html as HtmlReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class Report Excel Builder.
 */
class ReportExcelBuilder {
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;
  /**
   * Member variable countryIso.
   *
   * @var string
   */
  protected $countryIso;
  /**
   * Member variable data.
   *
   * @var array
   */
  protected $data;

  /**
   * Function __construct.
   */
  public function __construct($twig, $countryIso, $data) {
    $this->twig = $twig;
    $this->countryIso = $countryIso;
    $this->data = $data;
  }

  /**
   * Function getSpreadsheet.
   */
  public function getSpreadsheet() {
    $reader = new HtmlReader();

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    if (is_null($this->countryIso)) {
      $this->renderEuOverview($reader, $spreadsheet);
      $this->renderEuWide($reader, $spreadsheet);
      $this->renderEuTopPerformingDomains($reader, $spreadsheet);
      $this->renderEuLeastPerformingDomains($reader, $spreadsheet);
    }
    else {
      $this->renderMsOverview($reader, $spreadsheet);
      $this->renderMsTopPerformingIndicators($reader, $spreadsheet);
      $this->renderMsTopPerformingIndicatorsDiff($reader, $spreadsheet);
      $this->renderMsLeastPerformingIndicators($reader, $spreadsheet);
      $this->renderMsLeastPerformingIndicatorsDiff($reader, $spreadsheet);
    }

    return $spreadsheet;
  }

  /**
   * Function renderEuOverview.
   */
  private function renderEuOverview($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/eu-overview-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Overview');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelEUOverview::styles($to_sheet);
    ReportExcelEUOverview::columnWidths($to_sheet);
  }

  /**
   * Function renderEuWide.
   */
  private function renderEuWide($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/eu-wide-indicators-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('EU Wide Indicators');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelEUWideIndicators::styles($to_sheet);
    ReportExcelEUWideIndicators::columnWidths($to_sheet);
  }

  /**
   * Function renderEuTopPerformingDomains.
   */
  private function renderEuTopPerformingDomains($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/eu-domains-of-excellence-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Top-performing domains');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelEUDomainsOfExcellence::styles($to_sheet);
    ReportExcelEUDomainsOfExcellence::columnWidths($to_sheet);
  }

  /**
   * Function renderEuLeastPerformingDomains.
   */
  private function renderEuLeastPerformingDomains($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/eu-domains-of-improvement-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Least-performing domains');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelEUDomainsOfImprovement::styles($to_sheet);
    ReportExcelEUDomainsOfImprovement::columnWidths($to_sheet);
  }

  /**
   * Function renderMsOverview.
   */
  private function renderMsOverview($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/ms-overview-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Overview');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelMSOverview::styles($to_sheet);
    ReportExcelMSOverview::columnWidths($to_sheet);
  }

  /**
   * Function renderMsTopPerformingIndicators.
   */
  private function renderMsTopPerformingIndicators($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/ms-domains-of-excellence-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Top Performing Indicators');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelMSDomainsOfExcellence::styles($to_sheet);
    ReportExcelMSDomainsOfExcellence::columnWidths($to_sheet);
  }

  /**
   * Function renderMsTopPerformingIndicatorsDiff.
   */
  private function renderMsTopPerformingIndicatorsDiff($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/ms-domains-of-excellence-diff-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Top Performing Indicators Dif');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelMSDomainsOfExcellenceDiff::styles($to_sheet);
    ReportExcelMSDomainsOfExcellenceDiff::columnWidths($to_sheet);
  }

  /**
   * Function renderMsLeastPerformingIndicators.
   */
  private function renderMsLeastPerformingIndicators($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/ms-domains-of-improvement-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Least Performing Indicators');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelMSDomainsOfImprovement::styles($to_sheet);
    ReportExcelMSDomainsOfImprovement::columnWidths($to_sheet);
  }

  /**
   * Function renderMsLeastPerformingIndicatorsDiff.
   */
  private function renderMsLeastPerformingIndicatorsDiff($reader, $spreadsheet) {
    $html = $this->twig->render('@enisa/export/ms-domains-of-improvement-diff-report.html.twig', $this->data);

    $from_sheet = $reader->loadFromString($html)->getActiveSheet();

    $to_sheet = $spreadsheet->createSheet();
    $to_sheet->setTitle('Least Performing Indicators Dif');

    ExportHelper::copyDataAndStyle($from_sheet, $to_sheet);

    ReportExcelMSDomainsOfImprovementDiff::styles($to_sheet);
    ReportExcelMSDomainsOfImprovementDiff::columnWidths($to_sheet);
  }

}
