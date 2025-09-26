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
        // Use a hash-href to signal client-side reload via JS for WikiArt.
        'reload_url' => Url::fromRoute('picture_of_the_day_d10.reload', ['source' => 'wikipaintings'])->toString(),
        'image' => '<a href="' . htmlspecialchars($link ?: $imageUrl, ENT_QUOTES) . '"><img class="w-100" src="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '"/></a>',
        'title' => $title,
        'description' => '',
        'author' => $author,
        'url' => $link ?: $imageUrl,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('PictureOfTheDay wikipaintings (Met) failed: @m', ['@m' => $e->getMessage()]);
      return FALSE;
    }
  }

  public function reload(string $source): array|false {
    return match ($source) {
      'wikipaintings' => $this->wikipaintings(),
      default => FALSE,
    };
  }
}
