<?php

namespace Drupal\poetrynews_d10\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Defines the 'poetrynews_resource' field type for Drupal 10.
 *
 * @FieldType(
 *   id = "poetrynews_resource",
 *   label = @Translation("Poetry News Resource"),
 *   description = @Translation("Stores external resource info: title, URL, publisher (resource), author."),
 *   default_widget = "poetrynews_widget",
 *   default_formatter = "poetrynews_formatter"
 * )
 */
class PoetryNewsItem extends FieldItemBase {

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) : array {
    $properties['link_title'] = DataDefinition::create('string')
      ->setLabel(t('Resource title'))
      ->setRequired(FALSE);

    $properties['link_url'] = DataDefinition::create('string')
      ->setLabel(t('Resource URL'))
      ->setRequired(FALSE);

    $properties['resource'] = DataDefinition::create('string')
      ->setLabel(t('Publisher name'))
      ->setRequired(FALSE);

    $properties['author'] = DataDefinition::create('string')
      ->setLabel(t('Author name'))
      ->setRequired(FALSE);

    return $properties;
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition) : array {
    return [
      'columns' => [
        'link_title' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'link_url' => [
          'type' => 'varchar',
          'length' => 2048,
        ],
        'resource' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'author' => [
          'type' => 'varchar',
          'length' => 255,
        ],
      ],
    ];
  }

  public function isEmpty() : bool {
    $value = $this->get('link_title')->getValue();
    $url = $this->get('link_url')->getValue();
    $resource = $this->get('resource')->getValue();
    $author = $this->get('author')->getValue();
    return ($value === NULL || $value === '')
      && ($url === NULL || $url === '')
      && ($resource === NULL || $resource === '')
      && ($author === NULL || $author === '');
  }

}
