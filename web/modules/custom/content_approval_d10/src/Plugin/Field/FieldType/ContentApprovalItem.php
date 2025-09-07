<?php

namespace Drupal\content_approval_d10\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type: content_approval (single-column int/boolean like D7).
 *
 * @FieldType(
 *   id = "content_approval",
 *   label = @Translation("Content approval field"),
 *   description = @Translation("Marks content that requires approval prior publication."),
 *   default_widget = "content_approval_widget",
 *   default_formatter = "content_approval_formatter"
 * )
 */
class ContentApprovalItem extends FieldItemBase {

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) : array {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(t('Needs approval'))
      ->setRequired(FALSE);
    return $properties;
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition) : array {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'The attached entity must be approved prior publication if value is set to 1',
        ],
      ],
    ];
  }

  public function isEmpty(): bool {
    // Match D7 behavior: field_is_empty returns FALSE (never empty),
    // so in D10 we mimic by considering it never empty.
    return FALSE;
  }
}
