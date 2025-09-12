<?php

namespace Drupal\pdfmaker_d10\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MemberPdfForm extends FormBase {

  public function __construct(
    protected \Drupal\pdfmaker_d10\Service\PdfMaker $pdfMaker,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('pdfmaker_d10.pdf_maker'));
  }

  public function getFormId() {
    return 'pdfmaker_d10_member_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $filter = (string) $form_state->getValue('author_filter', '');
    $author_list = $this->getMemberList($filter);

    $form['type'] = [
      '#type' => 'hidden',
      '#value' => 'member',
    ];

    $form['author_filter'] = [
      '#title' => $this->t('Filter authors'),
      '#description' => $this->t('Use % character to specify filter:' ) . '<br />' .
        $this->t('&nbsp;&nbsp;@ex1 - filter names starts with «William»', ['@ex1' => 'William%']) . '<br />' .
        $this->t('&nbsp;&nbsp;@ex2 - filter names ends with «William»', ['@ex2' => '%William']),
      '#type' => 'textfield',
      '#ajax' => [
        'callback' => '::authorFilterCallback',
        'wrapper' => 'author-select',
        'event' => 'keyup',
      ],
      '#weight' => 0,
    ];

    $form['author'] = [
      '#title' => empty($filter) ? $this->t('Author') : $this->t('Authors like @f', ['@f' => $filter]),
      '#type' => 'select',
      '#options' => $author_list,
      '#empty_option' => empty($author_list) ? $this->t('- No match -') : $this->t('- Select author -'),
      '#prefix' => '<div id="author-select">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::authorSelectCallback',
        'wrapper' => 'author-associated-info',
      ],
      '#weight' => 1,
      '#required' => TRUE,
    ];

    $poem_list = [];
    $description = '';
    $title = '';

    $author_id = (int) $form_state->getValue('author');
    if ($author_id) {
      $poem_list = $this->getMemberPoemsList($author_id);
      $description = $this->getMemberBio($author_id);
      $member_name = $this->getMemberName($author_id);
      $title = 'Poetry of ' . $member_name;
      // Reset defaults to reflect selection.
      $form_state->set('selected_author', $author_id);
    }

    $form['wrapper'] = [
      '#prefix' => '<div id="author-associated-info">',
      '#suffix' => '</div>',
      '#weight' => 50,
    ];

    $form['wrapper']['title'] = [
      '#title' => $this->t('Title of the book'),
      '#description' => $this->t('Type in the text which will be placed on front page of PDF file and will be its name.'),
      '#type' => 'textfield',
      '#default_value' => $title,
      '#weight' => 20,
    ];

    $form['wrapper']['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description for the book (text only)'),
      '#default_value' => $description,
      '#format' => 'basic_html',
      '#weight' => 30,
    ];

    $form['wrapper']['poems'] = [
      '#title' => $this->t('Select which poems will appear in the book'),
      '#title_display' => empty($poem_list) ? 'invisible' : 'before',
      '#type' => 'checkboxes',
      '#options' => $poem_list,
      '#default_value' => array_keys($poem_list),
      '#weight' => 40,
      '#required' => TRUE,
    ];

    $form['wrapper']['author_pen_name'] = [
      '#title' => $this->t('Pen name'),
      '#type' => 'textfield',
      '#default_value' => $this->getMemberName($author_id) ?: '',
      '#required' => TRUE,
      '#weight' => 5,
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

  public function authorFilterCallback(array &$form, FormStateInterface $form_state) {
    return $form['author'];
  }

  public function authorSelectCallback(array &$form, FormStateInterface $form_state) {
    return $form['wrapper'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $author_id = (int) $form_state->getValue('author');
    $pen_name = trim((string) $form_state->getValue('author_pen_name'));
    $title = trim((string) $form_state->getValue(['title']));
    if ($title === '') {
      $title = 'Poetry of ' . $this->getMemberName($author_id);
    }
    $description = (string) ($form_state->getValue(['description'])['value'] ?? '');
    $poems = array_keys(array_filter((array) $form_state->getValue('poems')));

    if (!empty($poems)) {
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
      $this->messenger()->addStatus($this->t('PDF has been generated.'));
    }
  }

  protected function getMemberList(string $filter = ''): array {
    $options = [];
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'member_poem');
    $nids = $query->execute();
    if ($nids) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $nodes = $storage->loadMultiple($nids);
      $uids = [];
      foreach ($nodes as $node) {
        $uids[$node->getOwnerId()] = TRUE;
      }
      $users = \Drupal\user\Entity\User::loadMultiple(array_keys($uids));
      foreach ($users as $user) {
        $name = $user->getDisplayName();
        if ($filter !== '') {
          // Convert SQL-like filter to PHP contains: replace % with '' and test.
          $needle = str_replace('%', '', $filter);
          if (stripos($name, $needle) === FALSE) {
            continue;
          }
        }
        $options[$user->id()] = $name;
      }
      asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    }
    return $options;
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

  protected function getMemberName(int $uid): string {
    $user = \Drupal\user\Entity\User::load($uid);
    return $user ? $user->getDisplayName() : '';
  }

  protected function getMemberBio(int $uid): string {
    $user = \Drupal\user\Entity\User::load($uid);
    if ($user && $user->hasField('field_bio') && !$user->get('field_bio')->isEmpty()) {
      return (string) $user->get('field_bio')->value;
    }
    return '';
  }
}
