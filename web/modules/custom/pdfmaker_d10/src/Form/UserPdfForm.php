<?php

namespace Drupal\pdfmaker_d10\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserPdfForm extends FormBase {

  public function __construct(
    protected \Drupal\pdfmaker_d10\Service\PdfMaker $pdfMaker,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pdfmaker_d10.pdf_maker')
    );
  }

  public function getFormId() {
    return 'pdfmaker_d10_user_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $account = $user ?: $this->currentUser();
    $uid = $account->id();

    $member_name = method_exists($account, 'getDisplayName') ? $account->getDisplayName() : $account->getAccountName();
    $title_prefix = 'Poetry of ';
    $title = $title_prefix . $member_name;

    $poem_list = $this->getMemberPoemsList($uid);

    if (empty($poem_list)) {
      $form['empty'] = ['#type' => 'item', '#markup' => $this->t("You don't have any poems yet. Make at least one and come back!")];
      return $form;
    }

    $form['author_id'] = [
      '#type' => 'hidden',
      '#value' => $uid,
    ];
    $form['pen_name'] = [
      '#title' => $this->t('Pen name'),
      '#type' => 'textfield',
      '#default_value' => $member_name,
      '#required' => TRUE,
      '#weight' => 5,
    ];
    $form['title'] = [
      '#title' => $this->t('Title of the book'),
      '#description' => $this->t('Type in the text which will be placed on front page of PDF file and will be its name.'),
      '#type' => 'textfield',
      '#default_value' => $title,
      '#weight' => 20,
    ];
    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description for the book (text only)'),
      '#default_value' => '',
      '#format' => 'basic_html',
      '#weight' => 30,
    ];
    $form['poems'] = [
      '#title' => $this->t('Select which poems will appear in the book'),
      '#title_display' => empty($poem_list) ? 'invisible' : 'before',
      '#type' => 'checkboxes',
      '#options' => $poem_list,
      '#default_value' => array_keys($poem_list),
      '#weight' => 40,
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Make PDF file'),
      '#button_type' => 'primary',
      '#weight' => 100,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $author_id = (int) $form_state->getValue('author_id');
    $pen_name = trim((string) $form_state->getValue('pen_name'));
    $title = trim((string) $form_state->getValue('title'));
    $description = (string) ($form_state->getValue('description')['value'] ?? '');
    $poems = array_keys(array_filter((array) $form_state->getValue('poems')));

    if ($title !== '' && $description !== '' && !empty($poems)) {
      $data = $this->pdfMaker->prepareBio($pen_name, $description);
      $data .= "\n#NP\n";
      $data .= "\n#AC\n";
      $data .= "\n1<Poems>\n";
      $data .= $this->pdfMaker->collectPoems($poems);

      $options = [
        'description' => 'Poetry Nook presents',
        'title' => $title,
        'folder' => 'member',
        'folder_inner' => (string) $author_id,
      ];
      $year = ', ' . date('Y');
      $rel = $this->pdfMaker->makePdf($data, $title . $year, $options);
      if (!$rel) {
        $this->messenger()->addError($this->t('PDF generation failed. Please contact the site administrator.'));
        return;
      }

      // Try to create a node similar to D7 behavior if possible.
      try {
        /** @var \Drupal\node\Entity\NodeType|null $type */
        $type = \Drupal::entityTypeManager()->getStorage('node_type')->load('ebook_member');
        if ($type) {
          $nodeValues = [
            'type' => 'ebook_member',
            'title' => $title,
            'uid' => $author_id,
          ];
          if (!empty($description)) {
            $nodeValues['body'] = [
              'value' => $description,
              'format' => 'basic_html',
            ];
          }
          /** @var \Drupal\node\Entity\Node $node */
          $node = \Drupal\node\Entity\Node::create($nodeValues);
          if ($rel) {
            $uri = 'private://' . ltrim($rel, '/');
            $data = @file_get_contents(\Drupal::service('file_system')->realpath($uri));
            if ($data !== FALSE) {
              $file = \Drupal::service('file.repository')->writeData($data, $uri, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
              if ($node->hasField('field_ebook_file')) {
                $node->set('field_ebook_file', [
                  'target_id' => $file->id(),
                  'display' => 0,
                ]);
              }
            }
          }
          $node->save();
        }
      }
      catch (\Throwable $e) {
        // Ignore if content type/field not present.
      }

      $this->messenger()->addStatus($this->t('You successfully created a PDF file.'));
    }
  }

  protected function getMemberPoemsList(int $member_id): array {
    $options = [];
    if ($member_id) {
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
        ->condition('uid', $member_id)
        ->condition('type', 'member_poem')
        ->sort('title');
      $nids = $query->execute();
      if ($nids) {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
        foreach ($nodes as $node) {
          $options[$node->id()] = $node->label();
        }
      }
    }
    return $options;
  }
}
