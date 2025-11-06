<?php

declare(strict_types=1);

namespace Drupal\ai_context\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\EditorInterface;
use Drupal\mcp\Plugin\McpPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MCP-aware AI CKEditor controller.
 */
class AiCKEditorMcpController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the controller.
   */
  public function __construct(
    protected readonly AiProviderPluginManager $aiProviderManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $account,
    protected readonly MessengerInterface $messenger,
    protected readonly McpPluginManager $mcpPluginManager,
    protected readonly ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('ai_context');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('plugin.manager.mcp'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Performs a request to AI with MCP support.
   */
  public function doRequest(Request $request, EditorInterface $editor, AiCKEditorPluginInterface $ai_ckeditor_plugin): StreamedResponse|Response {
    $data = json_decode($request->getContent());
    $config = $this->configFactory->get('ai_context.settings');
    $mcp_mode = $config->get('mcp_mode') ?? 'direct';

    $this->logger->info('🎯 MCP Mode: @mode', ['@mode' => $mcp_mode]);

    try {
      // Get AI provider and model.
      [$ai_provider, $ai_model] = $this->getProviderAndModel($editor, $ai_ckeditor_plugin);

      // Prepare prompt with HTML restrictions.
      $prompt = $this->preparePrompt($data->prompt, $editor);

      if ($mcp_mode === 'full') {
        return $this->handleFullMcpMode($prompt, $ai_provider, $ai_model);
      }
      else {
        return $this->handleDirectMcpMode($prompt, $ai_provider, $ai_model);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('MCP Controller error: @message', ['@message' => $e->getMessage()]);
      return new Response("The request could not be completed: " . $e->getMessage(), Response::HTTP_BAD_REQUEST);
    }
  }

  /**
   * Handle Full MCP mode with function calling.
   */
  protected function handleFullMcpMode(string $prompt, $ai_provider, string $ai_model): Response {
    $this->logger->info('🔄 MCP Full: Starting function calling flow');

    $messages = [new ChatMessage('user', $prompt)];
    $max_iterations = $this->configFactory->get('ai_context.settings')->get('max_tool_iterations') ?? 3;
    $iteration = 0;

    while ($iteration < $max_iterations) {
      $iteration++;
      $this->logger->info('🔄 MCP Full: Iteration @iter', ['@iter' => $iteration]);

      // Create chat input.
      $chat_input = new ChatInput($messages);
      $system_prompt = 'You are a helpful website assistant for content writing and editing. '
        . 'When the user asks for content with internal links, you MUST use the search_drupal_content tool to find real content on the site. '
        . 'Make comprehensive searches that cover ALL topics mentioned in the user request. '
        . 'If the user mentions multiple topics (e.g., "Portuguese and French cuisine"), consider making separate searches for each topic to ensure complete coverage. '
        . 'Always use the exact URLs returned by the tool - never invent URLs. '
        . 'Create meaningful anchor text based on the actual article titles returned.';
      $chat_input->setSystemPrompt($system_prompt);

      // Add MCP tools on first iteration.
      if ($iteration === 1) {
        $tools = $this->getMcpToolsAsChatTools();
        if (!empty($tools)) {
          $chat_input->setChatTools(new ToolsInput($tools));
          $tool_names = array_map(fn($t) => $t->getName(), $tools);
          $this->logger->info('✅ MCP Full: @count tools exposed: @tools', [
            '@count' => count($tools),
            '@tools' => implode(', ', $tool_names),
          ]);
        }
      }

      // Call AI provider.
      $this->logger->info('📡 Calling AI provider with @msg_count messages', [
        '@msg_count' => count($messages),
      ]);
      
      $response = $ai_provider->chat($chat_input, $ai_model, ['ai_ckeditor']);
      $normalized = $response->getNormalized();

      // Debug: Log the response type and tools.
      $tools = $normalized->getTools();
      $response_text_preview = substr($normalized->getText(), 0, 100);
      $this->logger->info('📥 AI Response: type=@type, tools=@tools, text_preview="@preview"', [
        '@type' => get_class($normalized),
        '@tools' => $tools ? count($tools) : 0,
        '@preview' => $response_text_preview,
      ]);

      // Check if there are tool calls.
      if (!empty($tools)) {
        $tool_calls = $tools;
        $this->logger->info('🛠️ MCP Full: @count tool calls received', ['@count' => count($tool_calls)]);

        // Execute tools and add results to messages.
        foreach ($tool_calls as $tool_call) {
          $tool_name = $tool_call->getName();
          
          // Convert ToolsPropertyResult[] to simple array.
          $arguments = [];
          foreach ($tool_call->getArguments() as $arg) {
            $arguments[$arg->getName()] = $arg->getValue();
          }

          $this->logger->info('⚙️ Executing tool: @tool with args: @args', [
            '@tool' => $tool_name,
            '@args' => json_encode($arguments),
          ]);

          $result = $this->executeMcpTool($tool_name, $arguments);
          
          // Parse and log detailed results.
          $result_preview = $result['result'] ?? '';
          if (is_string($result_preview)) {
            $result_data = json_decode($result_preview, TRUE);
            if ($result_data && isset($result_data['results'])) {
              $found_count = count($result_data['results']);
              $found_urls = array_map(fn($r) => $r['url'] . ' (score: ' . $r['score'] . ')', $result_data['results']);
              $this->logger->info('📦 MCP Tool returned @count results: @urls', [
                '@count' => $found_count,
                '@urls' => implode(', ', $found_urls),
              ]);
            }
            else {
              $this->logger->info('📦 Tool result (raw): @result', [
                '@result' => substr($result_preview, 0, 300),
              ]);
            }
          }
          
          // Add assistant message with tool call.
          $assistant_msg = new ChatMessage('assistant', '');
          $assistant_msg->setTools([$tool_call]);
          $messages[] = $assistant_msg;
          
          // Add tool response message.
          $tool_msg = new ChatMessage('tool', json_encode($result));
          $tool_msg->setToolsId($tool_call->getToolId());
          $messages[] = $tool_msg;
          
          $this->logger->info('💬 Message history now contains @count messages (user + assistant + tool responses)', [
            '@count' => count($messages),
          ]);
        }

        // Continue to next iteration.
        $this->logger->info('🔁 Continuing to iteration @next with tool results', [
          '@next' => $iteration + 1,
        ]);
        continue;
      }

      // No tool calls, we have the final response.
      $final_text = $normalized->getText();
      $final_preview = substr($final_text, 0, 150);
      $link_count = substr_count($final_text, '<a href=');
      
      $this->logger->info('✅ MCP Full: Final response | Length: @length chars | Links found: @links | Preview: "@preview"', [
        '@length' => strlen($final_text),
        '@links' => $link_count,
        '@preview' => $final_preview,
      ]);
      
      return new Response($final_text);
    }

    // Max iterations reached.
    $this->logger->warning('⚠️ MCP Full: Max iterations reached');
    return new Response("Maximum tool iterations reached.", Response::HTTP_INTERNAL_SERVER_ERROR);
  }

  /**
   * Handle Direct MCP mode with single call.
   */
  protected function handleDirectMcpMode(string $prompt, $ai_provider, string $ai_model): Response {
    $this->logger->info('⚡ MCP Direct: Single call mode');

    // Call search_drupal_content directly with the prompt.
    try {
      $search_plugin = $this->mcpPluginManager->createInstance('search_api_content');
      $results = $search_plugin->executeTool('search_drupal_content', [
        'query' => $prompt,
        'limit' => 5,
      ]);

      $search_text = $results['content'][0]['text'] ?? '[]';
      $search_data = json_decode($search_text, TRUE);

      // Add results to context.
      $context_addition = "\n\nRELEVANT CONTENT FROM SITE:\n";
      if (!empty($search_data['results'])) {
        foreach ($search_data['results'] as $result) {
          $context_addition .= "- {$result['title']} → {$result['url']}\n";
          if (!empty($result['excerpt'])) {
            $context_addition .= "  Excerpt: {$result['excerpt']}\n";
          }
        }
        $context_addition .= "\nUSE ONLY THESE URLS for internal links.\n";
      }

      $enriched_prompt = $context_addition . "\nUSER REQUEST:\n" . $prompt;

      $this->logger->info('✅ MCP Direct: Context enriched with @count results', [
        '@count' => count($search_data['results'] ?? []),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('MCP Direct search failed: @message', ['@message' => $e->getMessage()]);
      $enriched_prompt = $prompt;
    }

    // Single call to AI with enriched context.
    $messages = new ChatInput([
      new ChatMessage('user', $enriched_prompt),
    ]);
    $messages->setSystemPrompt('You are a helpful website assistant for content writing and editing.');

    $response = $ai_provider->chat($messages, $ai_model, ['ai_ckeditor']);
    return new Response($response->getNormalized()->getText());
  }

  /**
   * Execute MCP tool.
   */
  protected function executeMcpTool(string $tool_name, array $arguments): array {
    // Extract plugin and tool from name (format: plugin_tool).
    if ($tool_name === 'search_drupal_content') {
      $plugin_id = 'search_api_content';
    }
    elseif (in_array($tool_name, ['get_current_context', 'get_related_content', 'suggest_internal_links', 'analyze_content_seo', 'get_content_style'])) {
      $plugin_id = 'drupal_context';
    }
    else {
      throw new \InvalidArgumentException("Unknown tool: {$tool_name}");
    }

    $this->logger->info('🔧 Loading MCP plugin: @plugin for tool: @tool', [
      '@plugin' => $plugin_id,
      '@tool' => $tool_name,
    ]);

    $plugin = $this->mcpPluginManager->createInstance($plugin_id);
    
    $start_time = microtime(TRUE);
    $result = $plugin->executeTool($tool_name, $arguments);
    $execution_time = round((microtime(TRUE) - $start_time) * 1000, 2);
    
    $this->logger->info('⚡ Tool executed in @time ms', ['@time' => $execution_time]);

    // Return the text content.
    return ['result' => $result['content'][0]['text'] ?? ''];
  }

  /**
   * Get MCP tools as ChatTools.
   */
  protected function getMcpToolsAsChatTools(): array {
    $chat_tools = [];
    $config = $this->configFactory->get('ai_context.settings');
    $enabled_plugins = $config->get('mcp_plugins') ?? [];

    foreach ($enabled_plugins as $plugin_id => $plugin_config) {
      if (empty($plugin_config['enabled'])) {
        continue;
      }

      try {
        $plugin = $this->mcpPluginManager->createInstance($plugin_id);

        if (!$plugin->checkRequirements()) {
          continue;
        }

        foreach ($plugin->getTools() as $tool) {
          $function = new ToolsFunctionInput($tool->name);
          $function->setDescription($tool->description);

          // Convert properties.
          $properties = [];
          if (!empty($tool->inputSchema['properties'])) {
            foreach ($tool->inputSchema['properties'] as $prop_name => $prop_data) {
              $property = new ToolsPropertyInput($prop_name);
              $property->setDescription($prop_data['description'] ?? '');
              $property->setType($prop_data['type'] ?? 'string');

              if (!empty($tool->inputSchema['required']) && in_array($prop_name, $tool->inputSchema['required'])) {
                $property->setRequired(TRUE);
              }

              // Handle array items for OpenAI schema validation.
              if (($prop_data['type'] ?? '') === 'array' && !empty($prop_data['items'])) {
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
        $this->logger->warning('Failed to load MCP plugin @plugin: @message', [
          '@plugin' => $plugin_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $chat_tools;
  }

  /**
   * Get provider and model.
   */
  protected function getProviderAndModel(EditorInterface $editor, AiCKEditorPluginInterface $plugin): array {
    $settings = $editor->getSettings();
    $configuration = $settings["plugins"]["ai_ckeditor_ai"]["plugins"];
    $preferred_model = $configuration[$plugin->getPluginId()]['provider'] ?? NULL;

    if ($preferred_model) {
      $ai_provider = $this->aiProviderManager->loadProviderFromSimpleOption($preferred_model);
      $ai_model = $this->aiProviderManager->getModelNameFromSimpleOption($preferred_model);
    }
    else {
      $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
      if (empty($default_provider['provider_id'])) {
        throw new \Exception('No AI provider configured');
      }
      $ai_provider = $this->aiProviderManager->createInstance($default_provider['provider_id']);
      $ai_model = $default_provider['model_id'];
    }

    return [$ai_provider, $ai_model];
  }

  /**
   * Prepare prompt with HTML restrictions.
   */
  protected function preparePrompt(string $prompt, EditorInterface $editor): string {
    $format = $editor->getFilterFormat();
    $restrictions = $format->getHtmlRestrictions();

    if (!empty($restrictions) && !empty($restrictions['allowed'])) {
      $allowed_tags = implode(' ', array_keys($restrictions['allowed']));
      $prompt = "Format the answer using ONLY the following HTML tags: {$allowed_tags}. " . $prompt;
    }
    else {
      $prompt = "Format the answer using basic HTML formatting tags. " . $prompt;
    }

    $prompt = "Do not try to use any image, video, or audio tags. Do not use backticks or ```html indicator. " . $prompt;

    return $prompt;
  }

}

