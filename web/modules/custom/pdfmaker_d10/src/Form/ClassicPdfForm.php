<?php

namespace Drupal\pdfmaker_d10\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClassicPdfForm extends FormBase {

  public function __construct(
    protected \Drupal\pdfmaker_d10\Service\PdfMaker $pdfMaker,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('pdfmaker_d10.pdf_maker'));
  }

  public function getFormId() {
    return 'pdfmaker_d10_classic_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $filter = (string) $form_state->getValue('author_filter', '');
    $author_list = $this->getClassicList($filter);

    $form['type'] = [
      '#type' => 'hidden',
      '#value' => 'classic',
    ];

    $form['author_filter'] = [
      '#title' => $this->t('Filter authors'),
      '#description' => $this->t('Use % character to specify filter:') . '<br />' .
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
      $poem_list = $this->getClassicPoemsList($author_id);
      $node = \Drupal\node\Entity\Node::load($author_id);
      if ($node) {
        if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
          $description = (string) $node->get('body')->value;
        }
        $title = 'Poetry of ' . $node->label();
      }
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
    $title = trim((string) $form_state->getValue(['title']));
    $description = (string) ($form_state->getValue(['description'])['value'] ?? '');
    $poems = array_keys(array_filter((array) $form_state->getValue('poems')));

    $poet_name = '';
    $birth = '';
    $death = '';
    $node = \Drupal\node\Entity\Node::load($author_id);
    if ($node) {
      $poet_name = $node->label();
      if ($description === '' && $node->hasField('body') && !$node->get('body')->isEmpty()) {
        $description = (string) $node->get('body')->value;
      }
      if ($node->hasField('field_birth_date_text') && !$node->get('field_birth_date_text')->isEmpty()) {
        $birth = (string) $node->get('field_birth_date_text')->value;
      }
      if ($node->hasField('field_death_date_text') && !$node->get('field_death_date_text')->isEmpty()) {
        $death = (string) $node->get('field_death_date_text')->value;
      }
    }

    $data = $this->pdfMaker->prepareBio($poet_name ?: 'Unknown Poet', $description, $birth, $death);
    $data .= "\n#NP\n";
    $data .= "\n#AC\n";
    $data .= "\n1<Poems>\n";
    $data .= $this->pdfMaker->collectPoems($poems);

    $book_title = $title !== '' ? $title : ('Poetry of ' . ($poet_name ?: 'Unknown Poet'));
    $options = [
      'title' => $book_title,
      'description' => 'Poetry Nook presents',
      'folder' => 'classic',
      'folder_inner' => (string) $author_id,
    ];
    $rel = $this->pdfMaker->makePdf($data, $book_title, $options);
    if (!$rel) {
      $this->messenger()->addError($this->t('PDF generation failed. Please contact the site administrator.'));
      return;
    }
    $this->messenger()->addStatus($this->t('PDF has been generated.'));
  }

  protected function getClassicList(string $filter = ''): array {
    $options = [];
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'poet')
      ->sort('title')
      ->range(0, 50);
    if ($filter !== '') {
      $needle = str_replace('%', '', $filter);
      // We cannot do SQL LIKE easily across DBs without wildcards; load titles and filter in PHP.
      $nids = $query->execute();
      if ($nids) {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
        foreach ($nodes as $node) {
          $title = $node->label();
          if (stripos($title, $needle) !== FALSE) {
            // Ensure poet has at least one poem referencing via field_author.
            if ($this->hasPoemsForPoet($node->id())) {
              $options[$node->id()] = $title;
            }
          }
        }
      }
      asort($options, SORT_NATURAL | SORT_FLAG_CASE);
      return $options;
    }
    else {
      $nids = $query->execute();
      if ($nids) {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
        foreach ($nodes as $node) {
          if ($this->hasPoemsForPoet($node->id())) {
            $options[$node->id()] = $node->label();
          }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
      }
      return $options;
    }
  }

  protected function hasPoemsForPoet(int $poet_nid): bool {
    $q = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'poem')
      ->condition('field_author.target_id', $poet_nid)
      ->range(0, 1);
    $ids = $q->execute();
    return !empty($ids);
  }

  protected function getClassicPoemsList(int $poet_id): array {
    $options = [];
    if ($poet_id) {
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
        ->condition('type', 'poem')
        ->condition('field_author.target_id', $poet_id)
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
