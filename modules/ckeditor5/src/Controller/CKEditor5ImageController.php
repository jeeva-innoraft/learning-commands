<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Controller;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\editor\Entity\Editor;
use Drupal\file\Upload\FileUploadHandler;
use Drupal\file\Upload\FileValidationException;
use Drupal\file\Upload\FormUploadedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Lock\Exception\LockAcquiringException;

/**
 * Returns response for CKEditor 5 Simple image upload adapter.
 *
 * @internal
 *   Controller classes are internal.
 */
class CKEditor5ImageController extends ControllerBase {

  /**
   * The default allowed image extensions.
   */
  const DEFAULT_IMAGE_EXTENSIONS = 'gif png jpg jpeg webp svg+xml svg';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The file upload handler.
   *
   * @var \Drupal\file\Upload\FileUploadHandler
   */
  protected $fileUploadHandler;

  /**
   * Constructs a new CKEditor5ImageController.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\Upload\FileUploadHandler $file_upload_handler
   *   The file upload handler.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   */
  public function __construct(FileSystemInterface $file_system, FileUploadHandler $file_upload_handler, LockBackendInterface $lock) {
    $this->fileSystem = $file_system;
    $this->fileUploadHandler = $file_upload_handler;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('file.upload_handler'),
      $container->get('lock'),
    );
  }

  /**
   * Uploads and saves an image from a CKEditor 5 POST.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON object including the file URL.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Thrown when file system errors occur.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown when validation errors occur.
   */
  public function upload(Request $request) {
    // Getting the UploadedFile directly from the request.
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $upload */
    $upload = $request->files->get('upload');
    $filename = $upload->getClientOriginalName();

    /** @var \Drupal\editor\EditorInterface $editor */
    $editor = $request->attributes->get('editor');
    $settings = $editor->getImageUploadSettings();
    $destination = $settings['scheme'] . '://' . $settings['directory'];

    // Check the destination file path is writable.
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Destination file path is not writable');
    }

    $validators = $this->getImageUploadValidators($settings);

    $file_uri = "{$destination}/{$filename}";
    $file_uri = $this->fileSystem->getDestinationFilename($file_uri, FileSystemInterface::EXISTS_RENAME);

    // Lock based on the prepared file URI.
    $lock_id = self::generateLockIdFromFileUri($file_uri);

    if (!$this->lock->acquire($lock_id)) {
      throw new HttpException(503, sprintf('File "%s" is already locked for writing.', $file_uri), NULL, ['Retry-After' => 1]);
    }

    try {
      $uploadedFile = new FormUploadedFile($upload);
      $uploadResult = $this->fileUploadHandler->handleFileUpload($uploadedFile, $validators, $destination, FileSystemInterface::EXISTS_RENAME);
    }
    catch (FileValidationException $e) {
      $exception_message = $e->getMessage();
      $exception_message .= "\n* " . implode("\n* ", $e->getErrors());
      throw new UnprocessableEntityHttpException($exception_message, $e);
    }
    catch (FileException $e) {
      throw new HttpException(500, 'File could not be saved');
    }
    catch (LockAcquiringException $e) {
      throw new HttpException(503, sprintf('File "%s" is already locked for writing.', $upload->getClientOriginalName()), NULL, ['Retry-After' => 1]);
    }

    $this->lock->release($lock_id);

    $file = $uploadResult->getFile();
    return new JsonResponse([
      'url' => $file->createFileUrl(),
      'uuid' => $file->uuid(),
      'entity_type' => $file->getEntityTypeId(),
    ], 201);
  }

  /**
   * Gets the image upload validators.
   */
  protected function getImageUploadValidators(array $settings): array {
    $max_filesize = min(Bytes::toNumber($settings['max_size']), Environment::getUploadMaxSize());
    $max_dimensions = 0;
    if (!empty($settings['max_dimensions']['width']) || !empty($settings['max_dimensions']['height'])) {
      $max_dimensions = $settings['max_dimensions']['width'] . 'x' . $settings['max_dimensions']['height'];
    }
    return [
      'file_validate_extensions' => [self::DEFAULT_IMAGE_EXTENSIONS],
      'file_validate_size' => [$max_filesize],
      'file_validate_image_resolution' => [$max_dimensions],
    ];
  }

  /**
   * Access check based on whether image upload is enabled or not.
   *
   * @param \Drupal\editor\Entity\Editor $editor
   *   The text editor for which an image upload is occurring.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function imageUploadEnabledAccess(Editor $editor) {
    if ($editor->getEditor() !== 'ckeditor5') {
      return AccessResult::forbidden();
    }
    if ($editor->getImageUploadSettings()['status'] !== TRUE) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Generates a lock ID based on the file URI.
   *
   * @param string $file_uri
   *   The file URI.
   *
   * @return string
   *   The generated lock ID.
   */
  protected static function generateLockIdFromFileUri($file_uri) {
    return 'file:ckeditor5:' . Crypt::hashBase64($file_uri);
  }

}
