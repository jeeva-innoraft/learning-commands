<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'field_deepl_glossary_item' formatter.
 *
 * @FieldFormatter(
 *   id = "deepl_glossary_item_formatter",
 *   module = "tmgmt_deepl_glossary",
 *   label = @Translation("DeepL glossary item formatter"),
 *   field_types = {
 *     "deepl_glossary_item"
 *   }
 * )
 */
class DeeplGlossaryItemFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Subject: @subject - Definition: @definition', [
          '@subject' => $item->subject,
          '@definition' => $item->definition,
        ]),
      ];
    }

    return $elements;
  }

}
