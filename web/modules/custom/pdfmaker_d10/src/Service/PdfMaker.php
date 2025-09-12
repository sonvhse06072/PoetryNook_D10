<?php

namespace Drupal\pdfmaker_d10\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * PDF Maker service that ports legacy functionality from Drupal 7 pdfmaker.
 */
class PdfMaker {

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected LoggerChannelInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileRepositoryInterface $fileRepository,
  ) {}

  /**
   * Collect poems content by node IDs.
   *
   * @param int[] $nids
   * @return string
   */
  public function collectPoems(array $nids): string {
    if (empty($nids)) {
      return '';
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $nodes = $storage->loadMultiple($nids);
    $data = '';
    foreach ($nodes as $node) {
      $title = (string) $node->label();
      // Try to get body value.
      $body = '';
      if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
        $body = (string) $node->get('body')->value;
      }
      $year = '';
      if ($node->hasField('field_year') && !$node->get('field_year')->isEmpty()) {
        $year = (string) $node->get('field_year')->value;
      }
      $data .= $this->preparePoem($title, $body, $year);
    }
    return $data;
  }

  /**
   * Prepare poet's bio text block.
   */
  public function prepareBio(string $poet, string $bio, string $birthDate = '', string $deathDate = ''): string {
    $output = '';
    $output .= '1<' . $poet . ">\n";

    if (preg_match('/(\d{2})\/(\d{2})\/(\d{2,4})/', $birthDate, $m)) {
      $birthDate = sprintf('%d %s %s', (int) $m[2], date('F', mktime(0,0,0,(int)$m[1],1)), $m[3]);
    }
    if (preg_match('/(\d{2})\/(\d{2})\/(\d{2,4})/', $deathDate, $m)) {
      $deathDate = sprintf('%d %s %s', (int) $m[2], date('F', mktime(0,0,0,(int)$m[1],1)), $m[3]);
    }

    if (!empty($birthDate) || !empty($deathDate)) {
      $dateLine = "\n";
      if (empty($deathDate)) {
        $dateLine .= 'Born ' . $birthDate;
      }
      elseif (empty($birthDate)) {
        $dateLine .= 'Died ' . $deathDate;
      }
      else {
        $dateLine .= $birthDate . ' - ' . $deathDate;
      }
      $output .= "#AC\n";
      $output .= $dateLine . "\n";
    }

    $output .= "\n#AL";
    if (!empty($bio)) {
      $bio = preg_replace('/<br\s?\/?\>/', "\n", $bio);
      $bio = preg_replace('/<p>(.*?)<\/p>/', '$1\n', $bio);
      $bio = strip_tags($bio);
      $lines = explode("\n", Html::decodeEntities($bio));
      foreach ($lines as $k => $line) {
        $lines[$k] = trim($line);
      }
      $lines = implode("\n", $lines);
      $output .= "\n" . $lines;
    }

    return $output;
  }

  /**
   * Prepare poem block.
   */
  public function preparePoem(string $title, string $poem, string $year = ''): string {
    $output = '';
    if ($title !== '') {
      $output .= "\n2<" . $title . ">\n\n";
    }
    if ($poem !== '') {
      $poem = preg_replace('/<br\s?\/?\>/', "\n", $poem);
      $poem = str_replace("\n\n", "\n", $poem);
      $poem = strip_tags($poem);
      $lines = explode("\n", Html::decodeEntities($poem));
      foreach ($lines as $k => $line) {
        $lines[$k] = trim($line);
      }
      $lines = implode("\n", $lines);
      $output .= $lines;
    }
    if ($year !== '') {
      if (str_contains($year, '-')) {
        $year = date('Y', strtotime($year));
      }
      $output .= $year === '' ? '' : "\n\n(" . $year . ")";
    }
    $output .= "\n#NP";
    return $output;
  }

  /**
   * Build and save a PDF file.
   * Returns the relative (private) path like 'pdf/.../file.pdf'.
   */
  public function makePdf(string $data, ?string $filename = NULL, array $options = []): ?string {
    // Include pdf-php library. Prefer this module's lib, fallback to legacy D7 module's lib.
    $lib = dirname(__DIR__, 2) . '/lib/Creport.php';
    if (!file_exists($lib)) {
      $legacy = (defined('DRUPAL_ROOT') ? DRUPAL_ROOT : dirname(__DIR__, 5)) . '/sites/all/modules/custom/pdfmaker/lib/Creport.php';
      if (file_exists($legacy)) {
        $lib = $legacy;
      }
    }
    if (!file_exists($lib)) {
      $this->logger->error('pdfmaker_d10: Missing pdf-php library (Creport.php). Checked @path.', ['@path' => $lib]);
      return NULL;
    }
    require_once $lib;

    $pdf = new \Creport('a4', 'portrait', 'none', NULL);
    $pdf->ezSetMargins(50, 70, 50, 50);

    $headers = [
      ['x' => 50, 'y' => 34, 'size' => 6, 'text' => 'Find more poetry at http://www.poetrynook.com'],
      ['x' => 50, 'y' => 28, 'size' => 6, 'text' => 'Made with http://www.sourceforge.net/p/pdf-php'],
    ];
    $this->headers($pdf, $headers);

    $front_text = [
      [
        'text' => $options['description'] ?? '',
        'size' => 20,
        'align' => 'centre',
        'offset' => -200,
      ],
      [
        'text' => $options['title'] ?? 'Poetry Nook Collection',
        'size' => 30,
        'align' => 'centre',
        'offset' => 0,
      ],
    ];
    $this->frontText($pdf, $front_text);

    $pdf->ezSetDy(-100);
    $pdf->openHere('Fit');
    $pdf->ezNewPage();
    $pdf->ezStartPageNumbers(500, 28, 10, '', '', 1);

    $this->parse($pdf, $data);

    $pdf->ezStopPageNumbers(1, 1);
    $this->tableOfContents($pdf);

    if (!$filename) {
      $filename = 'Poetry Nook Collection';
    }
    $filename = $this->sanitizeFilename($filename);

    $folder = $options['folder'] ?? '';
    $folder_inner = $options['folder_inner'] ?? '';
    return $this->save($pdf, $filename, $folder, $folder_inner);
  }

  protected function frontText(\Creport $pdf, array $options): void {
    if (empty($options)) { return; }
    foreach ($options as $option) {
      $pdf->ezSetDy($option['offset']);
      $pdf->ezText($option['text'] . "\n", $option['size'], ['justification' => $option['align']]);
    }
    $pdf->ezSetDy(-100);
  }

  protected function tableOfContents(\Creport $pdf): void {
    $pdf->ezInsertMode(1, 1, 'after');
    $pdf->ezNewPage();
    $pdf->ezText("Contents\n", 26, ['justification' => 'centre']);
    $xpos = 520;
    $contents = $pdf->reportContents;
    foreach ($contents as $k => $v) {
      switch ($v[2]) {
        case '1':
          $pdf->ezText('<c:ilink:toc' . $k . '>' . $v[0] . '</c:ilink><C:dots:1' . $v[1] . '>', 16, ['aright' => $xpos]);
          break;
        case '2':
          $pdf->ezText('<c:ilink:toc' . $k . '>' . $v[0] . '</c:ilink><C:dots:2' . $v[1] . '>', 12, ['left' => 50, 'aright' => $xpos]);
          break;
      }
    }
  }

  protected function headers(\Creport $pdf, array $options = []): void {
    if (empty($options)) { return; }
    $all = $pdf->openObject();
    $pdf->saveState();
    $pdf->setStrokeColor(0, 0, 0, 1);
    $pdf->line(20, 40, 578, 40);
    $pdf->line(20, 822, 578, 822);
    foreach ($options as $option) {
      $pdf->addText($option['x'], $option['y'], $option['size'], $option['text']);
    }
    $pdf->restoreState();
    $pdf->closeObject();
    $pdf->addObject($all, 'next');
  }

  /**
   * Parse the custom markup and write to PDF.
   */
  protected function parse(\Creport $pdf, string $data): void {
    $modulePath = dirname(__DIR__, 2);
    $mainFont = $modulePath . '/lib/fonts/Times-Roman.afm';
    $codeFont = $modulePath . '/lib/fonts/Courier.afm';
    $hasMain = file_exists($mainFont);
    $hasCode = file_exists($codeFont);
    if (!$hasMain || !$hasCode) {
      // Fallback to legacy module fonts if available.
      $legacyBase = (defined('DRUPAL_ROOT') ? DRUPAL_ROOT : dirname(__DIR__, 5)) . '/sites/all/modules/custom/pdfmaker/lib/fonts';
      $legacyMain = $legacyBase . '/Times-Roman.afm';
      $legacyCode = $legacyBase . '/Courier.afm';
      if (file_exists($legacyMain)) { $mainFont = $legacyMain; $hasMain = TRUE; }
      if (file_exists($legacyCode)) { $codeFont = $legacyCode; $hasCode = TRUE; }
    }
    if (!$hasMain) { $mainFont = 'Times-Roman'; }
    if (!$hasCode) { $codeFont = 'Courier'; }

    // Ensure a default font is selected.
    $pdf->selectFont($mainFont);

    $size = 12;
    $textOptions = ['justification' => 'centre'];
    $collecting = FALSE;
    $code = '';

    $lines = explode("\n", $data);
    foreach ($lines as $line) {
      $line = rtrim($line, "\r\n");
      if (strlen($line) && $line[0] == '#') {
        switch ($line) {
          case '#AL':
            $textOptions = ['justification' => 'left'];
            break;
          case '#AC':
            $textOptions = ['justification' => 'centre'];
            break;
          case '#NP':
            $pdf->ezNewPage();
            break;
          case '#C':
            $pdf->selectFont($codeFont);
            $textOptions = ['justification' => 'left', 'left' => 20, 'right' => 20];
            $size = 10;
            break;
          case '#c':
            $pdf->selectFont($mainFont);
            $textOptions = ['justification' => 'full'];
            $size = 12;
            break;
          case '#X':
            // Start legacy code block collection (ignored for security).
            $collecting = TRUE;
            $code = '';
            break;
          case '#x':
            // SECURITY: do not execute arbitrary code blocks. Log and ignore.
            $this->logger->warning('pdfmaker_d10: Ignored legacy #X/#x code block in PDF content.');
            $pdf->selectFont($mainFont);
            $code = '';
            $collecting = FALSE;
            break;
        }
      }
      elseif ($collecting) {
        // Ignore collected code lines.
        continue;
      }
      elseif ((strlen($line) > 1 && $line[1] === '<') && $line[strlen($line) - 1] === '>') {
        switch ($line[0]) {
          case '1':
            $tmp = substr($line, 2, strlen($line) - 3);
            $tmp2 = $tmp . '<C:rf:1' . rawurlencode($tmp) . '>';
            $pdf->ezText($tmp2, 26, ['justification' => 'centre']);
            break;
          default:
            $tmp = substr($line, 2, strlen($line) - 3);
            $tmp2 = $tmp . '<C:rf:2' . rawurlencode($tmp) . '>';
            $pdf->ezText($tmp2, 18, ['justification' => 'left']);
            break;
        }
      }
      else {
        $pdf->ezText($line, $size, $textOptions);
      }
    }
  }

  /**
   * Save the PDF to private://pdf/[folder]/[folder_inner]/filename.pdf
   * and return the relative path from private:// (e.g. 'pdf/x/y/file.pdf').
   */
  protected function save(\Creport $pdf, string $filename, string $folder = '', string $folder_inner = ''): ?string {
    $parts = array_filter(['pdf', $folder, $folder_inner]);
    $dir = 'private://' . implode('/', $parts) . '/';
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $target = $dir . $filename . '.pdf';
    try {
      $data = $pdf->ezOutput();
      $this->fileRepository->writeData($data, $target, FileSystemInterface::EXISTS_REPLACE);
      // Return path relative to private:// like original module did.
      return ltrim(str_replace('private://', '', $target), '/');
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to save PDF: @m', ['@m' => $e->getMessage()]);
      return NULL;
    }
  }

  protected function sanitizeFilename(string $name): string {
    $name = Html::decodeEntities($name);
    // Replace forbidden/problematic characters.
    $name = preg_replace('/[^\w\s\-\.,\(\)&]/u', '_', $name);
    // Collapse spaces and trim.
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    // Limit length.
    if (function_exists('mb_substr')) {
      $name = mb_substr($name, 0, 150);
    }
    else {
      $name = substr($name, 0, 150);
    }
    return $name === '' ? 'Poetry Nook Collection' : $name;
  }

}
