<?php

declare(strict_types=1);

namespace Drupal\ai_context\Service;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\mcp\Plugin\McpPluginManager;

/**
 * Decorator for AI provider to inject MCP tools.
 */
class AiProviderDecorator {

  /**
   * Constructs an AiProviderDecorator.
   *
   * @param \Drupal\ai\AiProviderPluginManager $decorated_provider
   *   The decorated provider.
   * @param \Drupal\mcp\Plugin\McpPluginManager $mcp_plugin_manager
   *   The MCP plugin manager.
   */
  public function __construct(
    protected AiProviderPluginManager $decoratedProvider,
    protected McpPluginManager $mcpPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): AiProviderInterface {
    $provider = $this->decoratedProvider->createInstance($plugin_id, $configuration);

    // Wrap the provider to intercept chat() calls.
    return new class($provider, $this->mcpPluginManager) implements AiProviderInterface {

      /**
       * Constructs the wrapper.
       */
      public function __construct(
        private AiProviderInterface $wrappedProvider,
        private McpPluginManager $mcpPluginManager,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function chat($input, string $model_id, array $tags = []) {
        // Only inject tools for CKEditor requests.
        if (in_array('ai_ckeditor', $tags) && $input instanceof ChatInput) {
          // Check if tools are not already set.
          if ($input->getChatTools() === NULL) {
            $mcp_tools = $this->getMcpToolsAsChatTools();
            if (!empty($mcp_tools)) {
              $input->setChatTools(new ToolsInput($mcp_tools));
            }
          }
        }

        return $this->wrappedProvider->chat($input, $model_id, $tags);
      }

      /**
       * Get MCP tools as ChatTools format.
       *
       * @return array
       *   Array of ToolsFunctionInput.
       */
      protected function getMcpToolsAsChatTools(): array {
        $chat_tools = [];

        try {
          // Get MCP plugins.
          $mcp_plugins = ['search_api_content', 'drupal_context'];

          foreach ($mcp_plugins as $plugin_id) {
            try {
              $plugin = $this->mcpPluginManager->createInstance($plugin_id);

              if (!$plugin->checkRequirements()) {
                continue;
              }

              // Get tools from the plugin.
              $tools = $plugin->getTools();

              foreach ($tools as $tool) {
                $function = new ToolsFunctionInput($tool->name);
                $function->setDescription($tool->description);

                // Convert input schema to properties.
                $properties = [];
                if (!empty($tool->inputSchema['properties'])) {
                  foreach ($tool->inputSchema['properties'] as $prop_name => $prop_data) {
                    $property = new ToolsPropertyInput($prop_name);
                    $property->setDescription($prop_data['description'] ?? '');
                    $property->setType($prop_data['type'] ?? 'string');

                    // Check if required.
                    if (!empty($tool->inputSchema['required']) && in_array($prop_name, $tool->inputSchema['required'])) {
                      $property->setRequired(TRUE);
                    }

                    // Handle enum.
                    if (!empty($prop_data['enum'])) {
                      $property->setEnum($prop_data['enum']);
                    }

                    // Handle array items.
                    if ($prop_data['type'] === 'array' && !empty($prop_data['items'])) {
                      $property->setItems($prop_data['items']);
                    }

                    $properties[$prop_name] = $property;
                  }
                }

                $function->setProperties($properties);
                $chat_tools[] = $function;
              }
            }
            catch (\Exception $e) {
              // Skip this plugin if it fails.
              continue;
            }
          }
        }
        catch (\Exception $e) {
          // If anything fails, return empty array.
          return [];
        }

        return $chat_tools;
      }

      /**
       * Forward all other methods to the wrapped provider.
       */
      public function __call($method, $arguments) {
        return call_user_func_array([$this->wrappedProvider, $method], $arguments);
      }

      /**
       * {@inheritdoc}
       */
      public function getConfiguredModels(string $operation_type = NULL, array $capabilities = []): array {
        return $this->wrappedProvider->getConfiguredModels($operation_type, $capabilities);
      }

      /**
       * {@inheritdoc}
       */
      public function isUsable(string $operation_type = NULL, array $capabilities = []): bool {
        return $this->wrappedProvider->isUsable($operation_type, $capabilities);
      }

      /**
       * {@inheritdoc}
       */
      public function getSupportedCapabilities(): array {
        return $this->wrappedProvider->getSupportedCapabilities();
      }

      /**
       * {@inheritdoc}
       */
      public function getPluginId() {
        return $this->wrappedProvider->getPluginId();
      }

      /**
       * {@inheritdoc}
       */
      public function getPluginDefinition() {
        return $this->wrappedProvider->getPluginDefinition();
      }

    };
  }

  /**
   * Forward all other methods to the decorated provider.
   */
  public function __call($method, $arguments) {
    return call_user_func_array([$this->decoratedProvider, $method], $arguments);
  }

}

