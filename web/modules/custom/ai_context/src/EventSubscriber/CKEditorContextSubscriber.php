<?php

declare(strict_types=1);

namespace Drupal\ai_context\EventSubscriber;

use Drupal\ai_context\Service\DrupalContextServiceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to enrich AI CKEditor requests with Drupal context.
 */
class CKEditorContextSubscriber implements EventSubscriberInterface {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs a CKEditorContextSubscriber.
   *
   * @param \Drupal\ai_context\Service\DrupalContextServiceInterface $contextService
   *   The Drupal context service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected DrupalContextServiceInterface $contextService,
    protected EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('ai_context');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

  /**
   * Handles the request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    // Only process AI CKEditor requests.
    if ($route_name !== 'ai_ckeditor.do_request') {
      return;
    }

    try {
      // Get the request data.
      $content = $request->getContent();
      if (empty($content)) {
        return;
      }

      $data = json_decode($content, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->warning('Failed to decode JSON request: @error', [
          '@error' => json_last_error_msg(),
        ]);
        return;
      }

      // Extract context information from the request.
      $options = [];

      if (!empty($data['entity_type'])) {
        $options['entity_type'] = $data['entity_type'];
      }

      if (!empty($data['entity_id'])) {
        $options['entity_id'] = $data['entity_id'];
      }

      // Get the plugin ID from route parameters.
      $plugin = $request->attributes->get('ai_ckeditor_plugin');
      if ($plugin) {
        $options['plugin'] = $plugin->getPluginId();
      }

      // Collect Drupal context.
      $context = $this->contextService->collectContext($options);

      // Enrich the prompt with context.
      if (!empty($data['prompt']) && !empty($context)) {
        $original_prompt = $data['prompt'];
        $enriched_prompt = $this->contextService->enrichPrompt($original_prompt, $context);

        // Update the request data.
        $data['prompt'] = $enriched_prompt;
        $data['_original_prompt'] = $original_prompt;
        $data['_context_applied'] = TRUE;

        // Replace request content.
        $request->request->replace($data);

        // Also update the raw content for controllers that read it directly.
        $new_content = json_encode($data);
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('content');
        $property->setAccessible(TRUE);
        $property->setValue($request, $new_content);

        $this->logger->info('Context enrichment applied to CKEditor AI request for plugin: @plugin', [
          '@plugin' => $options['plugin'] ?? 'unknown',
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error enriching CKEditor request with context: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}

