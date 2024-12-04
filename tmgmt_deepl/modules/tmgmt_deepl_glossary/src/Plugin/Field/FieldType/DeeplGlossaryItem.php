<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'deepl_glossary_item' field type.
 *
 * @FieldType(
 *   id = "deepl_glossary_item",
 *   label = @Translation("DeepL glossary item"),
 *   module = "tmgmt_deepl_glossary",
 *   default_widget = "deepl_glossary_item_widget",
 *   default_formatter = "deepl_glossary_item_formatter"
 * )
 */
class DeeplGlossaryItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'subject' => [
          'type' => 'text',
          'size' => 'normal',
          'not null' => FALSE,
        ],
        'definition' => [
          'type' => 'text',
          'size' => 'normal',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $subject = $this->get('subject')->getValue();
    $definition = $this->get('definition')->getValue();
    return ($subject === NULL || $subject === '') || ($definition === NULL || $definition === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];
    // The subject of the glossary item.
    $properties['subject'] = DataDefinition::create('string')
      ->setLabel(t('Subject of glossary item.'));

    // The definition of the glossary item.
    $properties['definition'] = DataDefinition::create('string')
      ->setLabel(t('Definition of glossary item'));

    return $properties;
  }

}
