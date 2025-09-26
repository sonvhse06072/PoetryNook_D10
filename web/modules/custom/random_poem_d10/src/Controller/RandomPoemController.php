<?php

namespace Drupal\random_poem_d10\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for random poem redirects.
 */
class RandomPoemController implements ContainerInjectionInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  public static function create(ContainerInterface $container): self {
    $instance = new self();
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * Redirects to a random poem in English (305) or American English (306).
   */
  public function random(): RedirectResponse {
    $nid = $this->getRandomNidByLang([305, 306]);
    return $this->redirectToNode($nid, ['random' => '0']);
  }

  /**
   * Redirects to a random Chinese poem (term 363).
   */
  public function randomChinese(): RedirectResponse {
    $nid = $this->getRandomNidByLang([363]);
    return $this->redirectToNode($nid, ['random' => '1']);
  }

  /**
   * Redirects to a random poem in languages other than English (305, 306) and Chinese (363).
   */
  public function randomOther(): RedirectResponse {
    $nid = $this->getRandomNidExcludingLang([]);
    return $this->redirectToNode($nid, ['random' => '4']);
  }

  /**
   * Redirects to a random Chinese poem (term 363) that has four lines (by character-length heuristic).
   */
  public function randomChineseFourLines(): RedirectResponse {
    // Using DB API to mimic original logic on D8/9/10 tables.
    $connection = $this->database;
    $query = $connection->select('node__field_language', 'l');
    $query->join('node__body', 'b', 'l.entity_id = b.entity_id AND l.deleted = 0 AND b.deleted = 0');
    // Compute length without <br /> tags, similar to D7 code.
    $query->addExpression("char_length(replace(b.body_value, '<br />', ''))", 'length');
    $query->fields('l', ['entity_id']);
    $query->condition('l.field_language_target_id', 363);
    $query->havingCondition($query->orConditionGroup()
      ->condition('length', 24)
      ->condition('length', 28)
    );
    $nids = $query->execute()->fetchCol();

    if (empty($nids)) {
      // Fallback to any Chinese poem if none matches.
      $nid = $this->getRandomNidByLang([363]);
    }
    else {
      $nid = $nids[array_rand($nids)];
    }

    return $this->redirectToNode($nid, ['random' => '2']);
  }

  /**
   * Redirects to a random Japanese poem (term 1703).
   */
  public function randomJapanese(): RedirectResponse {
    $connection = $this->database;
    $query = $connection->select('node_field_data', 'n');
    $query->join('node__field_language', 'f', 'n.nid = f.entity_id AND f.deleted = 0');
    $query->fields('n', ['nid']);
    $query->condition('n.type', 'poem');
    $query->condition('n.status', 1);
    $query->condition('f.field_language_target_id', 1703);
    $nids = $query->execute()->fetchCol();

    if (empty($nids)) {
      // If none found, just go to front page as a safe fallback.
      return new RedirectResponse('/');
    }

    $nid = $nids[array_rand($nids)];
    return $this->redirectToNode($nid, ['random' => '3']);
  }

  /**
   * Helper: Get a random nid by including languages.
   *
   * @param int[] $tids
   *   Language term IDs to include.
   */
  protected function getRandomNidByLang(array $tids): ?int {
    $connection = $this->database;
    $query = $connection->select('node_field_data', 'n');
    $query->join('node__field_language', 'f', 'n.nid = f.entity_id AND f.deleted = 0');
    $query->fields('n', ['nid']);
    $query->condition('n.type', 'poem');
    $query->condition('n.status', 1);
    $query->condition('f.field_language_target_id', $tids, 'IN');
    $query->range(0, 1);
    $query->orderRandom();
    $nid = $query->execute()->fetchField();
    return $nid ? (int) $nid : null;
  }

  /**
   * Helper: Get a random nid excluding languages.
   *
   * @param int[] $tids
   *   Language term IDs to exclude.
   */
  protected function getRandomNidExcludingLang(array $tids): ?int {
    $connection = $this->database;
    $query = $connection->select('node_field_data', 'n');
    $query->join('node__field_language', 'f', 'n.nid = f.entity_id AND f.deleted = 0');
    $query->fields('n', ['nid']);
    $query->condition('n.type', 'poem');
    $query->condition('n.status', 1);
    // Exclude provided language term IDs.
    foreach ($tids as $tid) {
      $query->condition('f.field_language_target_id', $tid, '<>');
    }
    $query->range(0, 1);
    $query->orderRandom();
    $nid = $query->execute()->fetchField();
    return $nid ? (int) $nid : null;
  }

  /**
   * Helper: Redirect to node with query params.
   */
  protected function redirectToNode(?int $nid, array $query = []): RedirectResponse {
    if (!$nid) {
      return new RedirectResponse('/');
    }
    $qs = http_build_query($query);
    $url = '/node/' . $nid . ($qs ? ('?' . $qs) : '');
    return new RedirectResponse($url);
  }
}
