<?php

namespace Drupal\tmgmt_deepl\Plugin\tmgmt\Translator;

use Drupal\tmgmt\Annotation\TranslatorPlugin;

/**
 * DeepL API Pro translator plugin.
 *
 * @TranslatorPlugin(
 *   id = "deepl_pro",
 *   label = @Translation("DeepL API Pro"),
 *   description = @Translation("DeepL API Pro Translator service."),
 *   ui = "Drupal\tmgmt_deepl\DeeplTranslatorUi",
 *   logo = "icons/deepl.svg",
 * )
 */
class DeeplProTranslator extends DeeplTranslator {

  /**
   * Translation service URL.
   *
   * @var string
   */
  protected string $translatorUrl = 'https://api.deepl.com/v2/translate';

  /**
   * Translation usage service URL.
   *
   * @var string
   */
  protected string $translatorUsageUrl = 'https://api.deepl.com/v2/usage';

  /**
   * Translation glossary service URL.
   *
   * @var string
   */
  protected string $translatorGlossaryUrl = 'https://api.deepl.com/v2/glossaries';

}
