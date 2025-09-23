<?php

namespace Drupal\picture_of_the_day_d10\Service;

use DOMDocument;
use DOMXPath;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Random;

class PictureOfTheDayService {

  protected ClientInterface $httpClient;
  protected LoggerInterface $logger;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(ClientInterface $http_client, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
  }

  public function getAll(): array {
    return [
      'wikipaintings' => $this->wikipaintings(),
      'wikimedia' => $this->wikimedia(),
      'google' => $this->google(),
    ];
  }

  public function wikipaintings(): array|false {
    // WikiArt JSON endpoints now require JS/cookies and often block server requests.
    // As an alternative, fetch a random public-domain painting from The Met Museum API.
    try {
      // 1) Search for paintings with images from the European Paintings department (id=11).
      $searchUrl = 'https://collectionapi.metmuseum.org/public/collection/v1/search?hasImages=true&departmentId=11&q=painting';
      $res = $this->httpClient->request('GET', $searchUrl, [
        'headers' => [
          'User-Agent' => 'Mozilla/5.0',
          'Accept' => 'application/json',
        ],
      ]);
      if ($res->getStatusCode() !== 200) {
        return FALSE;
      }
      $data = json_decode((string) $res->getBody(), true);
      if (!is_array($data) || empty($data['objectIDs']) || !is_array($data['objectIDs'])) {
        return FALSE;
      }
      // 2) Pick a random object ID and fetch its details.
      $objectId = $data['objectIDs'][array_rand($data['objectIDs'])];
      $objRes = $this->httpClient->request('GET', 'https://collectionapi.metmuseum.org/public/collection/v1/objects/' . $objectId, [
        'headers' => [
          'User-Agent' => 'Mozilla/5.0',
          'Accept' => 'application/json',
        ],
      ]);
      if ($objRes->getStatusCode() !== 200) {
        return FALSE;
      }
      $obj = json_decode((string) $objRes->getBody(), true);
      if (!is_array($obj)) {
        return FALSE;
      }
      $imageUrl = $obj['primaryImageSmall'] ?: ($obj['primaryImage'] ?? '');
      $title = $obj['title'] ?? '';
      $author = $obj['artistDisplayName'] ?? '';
      $link = $obj['objectURL'] ?? '';

      if (!$imageUrl) {
        return FALSE;
      }

      return [
        'tab_id' => 'wikipaintings',
        // Use a hash-href to signal client-side reload via JS for WikiArt.
        'reload_url' => Url::fromRoute('picture_of_the_day_d10.reload', ['source' => 'wikipaintings'])->toString(),
        'image' => '<a href="' . htmlspecialchars($link ?: $imageUrl, ENT_QUOTES) . '"><img class="w-100" src="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '"/></a>',
        'title' => $title,
        'description' => '',
        'author' => $author,
        'url' => $link ?: $imageUrl,
        'class' => 'show active',
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('PictureOfTheDay wikipaintings (Met) failed: @m', ['@m' => $e->getMessage()]);
      return FALSE;
    }
  }

  public function wikimedia(): array|false {
    try {
      $base = 'https://commons.wikimedia.org';
      $url = $base . '/wiki/Category:Paintings';
      $res = $this->httpClient->request('GET', $url);

      if ($res->getStatusCode() !== 200) {
        return FALSE;
      }
      $html = (string) $res->getBody();

      $dom = new DOMDocument();
      libxml_use_internal_errors(true);
      $dom->loadHTML($html);
      libxml_clear_errors();
      $xpath = new DOMXPath($dom);
      $galleryItems = $xpath->query('//li[@class="gallerybox"]');
      $img_element = NULL;
      $imageName = NULL;
      $fileLink = NULL;
      if ($galleryItems->length > 0) {
        $randomIndex = rand(0, $galleryItems->length - 1);
        $selectedItem = $galleryItems->item($randomIndex);
        $img = $selectedItem->getElementsByTagName('img')->item(0);
        $img->setAttribute('class', 'w-100');
        $linkTag = $xpath->query('.//a[contains(@class, "galleryfilename")]', $selectedItem)->item(0);
        $fileLink = $linkTag ? $linkTag->getAttribute('href') : 'N/A';
        $imageName = $linkTag ? $linkTag->nodeValue : 'N/A';

        if ($img) {
          $img_element = $dom->saveHTML($img);
        }
      }

      $item = [
        'tab_id' => 'wikimedia',
        'reload_url' => Url::fromRoute('picture_of_the_day_d10.reload', ['source' => 'wikimedia'])->toString(),
        'image' => '<a href="' . $base . $fileLink . '">' . $img_element . '</a>',
        'title' => '<a href="' . $base . $fileLink . '">' . htmlspecialchars($imageName ?: 'View', ENT_QUOTES) . '</a>',
        'description' => '',
        'author' => '',
        'url' => $base . $fileLink,
      ];

      // Fetch details page to try to get description and author.
      $res2 = $this->httpClient->request('GET', $base . $fileLink);
      if ($res2->getStatusCode() === 200) {
        $html2 = (string) $res2->getBody();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html2);
        libxml_clear_errors();
        // Use XPath to extract data
        $xpath = new DOMXPath($dom);
        $info_table = $xpath->query('//table[@class="fileinfotpl-type-information vevent"]');
        if ($info_table->length > 0) {
          $table = $info_table->item(0);
          $description_path = $xpath->query('.//td[contains(@class, "description")]', $table)->item(0);
          $description = $description_path ? $description_path->nodeValue : '';
          $author_row = $xpath->query('.//tr', $table)->item(3);
          $author_path = $xpath->query('.//td', $author_row)->item(1);
          $author = $author_path ? $author_path->nodeValue : '';
          $item['description'] = $description;
          $item['author'] = $author;
        }
      }
      return $item;
    }
    catch (\Throwable $e) {
      $this->logger->error('Wikimedia fetch failed: @m', ['@m' => $e->getMessage()]);
      return FALSE;
    }
  }

  public function google(): array|false {
    // This mirrors legacy behavior: builds a random query and uses Google Images page HTML.
    $config = $this->configFactory->get('picture_of_the_day_d10.settings');
    $query_tpl = (string) $config->get('google_search');
    $trials = (int) ($config->get('trial_count') ?? 3);
    if (!$query_tpl) {
      return [
        'tab_id' => 'google',
        'reload_url' => Url::fromRoute('picture_of_the_day_d10.reload', ['source' => 'google'])->toString(),
        'image' => '',
        'title' => 'No query configured',
        'description' => '',
        'author' => '',
        'url' => '',
      ];
    }
    while ($trials-- > 0) {
      $query = $this->applyWildcards($query_tpl);
      $url = 'https://www.google.com/search?q=' . rawurlencode($query) . '&tbm=isch';
      try {
        $res = $this->httpClient->request('GET', $url, [
          'headers' => [
            'User-Agent' => 'Mozilla/5.0',
            'Accept-Language' => 'en-US,en;q=0.9',
          ],
          'timeout' => 8,
        ]);
        if ($res->getStatusCode() !== 200) continue;
        $html = (string) $res->getBody();
        // Try to capture first image URL from Google images results; heuristic and may break.
        if (preg_match('/<img[^>]+src="(https?:\\/\\/[^\"]+)"/i', $html, $m)) {
          $img = html_entity_decode(stripslashes($m[1]));
          return [
            'tab_id' => 'google',
            'reload_url' => Url::fromRoute('picture_of_the_day_d10.reload', ['source' => 'google'])->toString(),
            'image' => '<a href="' . $img . '"><img class="w-100" src="' . $img . '"/></a>',
            'title' => $query,
            'description' => '',
            'author' => '',
            'url' => $img,
          ];
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Google fetch trial failed: @m', ['@m' => $e->getMessage()]);
      }
    }
    $empty = (string) ($this->configFactory->get('picture_of_the_day_d10.settings')->get('empty_message') ?? 'Oops, something went wrong. Try again!');
    return [
      'tab_id' => 'google',
      'reload_url' => Url::fromRoute('picture_of_the_day_d10.reload', ['source' => 'google'])->toString(),
      'image' => '',
      'title' => $empty,
      'description' => '',
      'author' => '',
      'url' => '',
    ];
  }

  public function reload(string $source): array|false {
    return match ($source) {
      'wikipaintings' => $this->wikipaintings(),
      'wikimedia' => $this->wikimedia(),
      'google' => $this->google(),
      default => FALSE,
    };
  }

  protected function applyWildcards(string $tpl): string {
    // Replace @number and @string placeholders similar to legacy.
    $r = new Random();
    $out = $tpl;
    // @number -> random 1..10000
    $out = preg_replace_callback('/@number/', fn() => (string) random_int(1, 10000), $out);
    // @string -> 5 random letters
    $out = preg_replace_callback('/@string/', fn() => strtolower($r->name(1)), $out);
    return $out;
  }
}
