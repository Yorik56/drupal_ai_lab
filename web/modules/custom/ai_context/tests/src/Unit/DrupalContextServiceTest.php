<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_context\Unit;

use Drupal\ai_context\Service\DrupalContextService;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for DrupalContextService.
 *
 * @coversDefaultClass \Drupal\ai_context\Service\DrupalContextService
 * @group ai_context
 */
class DrupalContextServiceTest extends UnitTestCase {

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cache;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The service under test.
   *
   * @var \Drupal\ai_context\Service\DrupalContextService
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cache = $this->createMock(CacheBackendInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->service = new DrupalContextService(
      $this->cache,
      $this->entityTypeManager,
      $this->configFactory,
      $this->currentUser,
      $this->moduleHandler
    );
  }

  /**
   * Tests enrichPrompt with empty context.
   *
   * @covers ::enrichPrompt
   */
  public function testEnrichPromptWithEmptyContext(): void {
    $prompt = 'Original prompt';
    $result = $this->service->enrichPrompt($prompt, []);

    $this->assertEquals($prompt, $result);
  }

  /**
   * Tests enrichPrompt with site context.
   *
   * @covers ::enrichPrompt
   */
  public function testEnrichPromptWithSiteContext(): void {
    $prompt = 'Write content';
    $context = [
      'site' => [
        'name' => 'Test Site',
        'slogan' => 'Test Slogan',
      ],
    ];

    $result = $this->service->enrichPrompt($prompt, $context);

    $this->assertStringContainsString('DRUPAL SITE CONTEXT:', $result);
    $this->assertStringContainsString('Test Site', $result);
    $this->assertStringContainsString('Test Slogan', $result);
    $this->assertStringContainsString('USER REQUEST:', $result);
    $this->assertStringContainsString($prompt, $result);
  }

  /**
   * Tests getCachedContext when cache is empty.
   *
   * @covers ::getCachedContext
   */
  public function testGetCachedContextEmpty(): void {
    $this->cache->expects($this->once())
      ->method('get')
      ->with('test_key')
      ->willReturn(FALSE);

    $result = $this->service->getCachedContext('test_key');
    $this->assertNull($result);
  }

  /**
   * Tests getCachedContext when cache has data.
   *
   * @covers ::getCachedContext
   */
  public function testGetCachedContextWithData(): void {
    $cached_data = ['site' => ['name' => 'Cached Site']];
    $cache_object = (object) ['data' => $cached_data];

    $this->cache->expects($this->once())
      ->method('get')
      ->with('test_key')
      ->willReturn($cache_object);

    $result = $this->service->getCachedContext('test_key');
    $this->assertEquals($cached_data, $result);
  }

  /**
   * Tests setCachedContext.
   *
   * @covers ::setCachedContext
   */
  public function testSetCachedContext(): void {
    $context = ['site' => ['name' => 'Test']];
    $cache_tags = ['test_tag'];
    $max_age = 3600;

    $this->cache->expects($this->once())
      ->method('set')
      ->with(
        'test_key',
        $context,
        $this->anything(),
        $cache_tags
      );

    $this->service->setCachedContext('test_key', $context, $cache_tags, $max_age);
  }

  /**
   * Tests collectContext basic functionality.
   *
   * @covers ::collectContext
   */
  public function testCollectContext(): void {
    // Mock site config.
    $site_config = $this->createMock(ImmutableConfig::class);
    $site_config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['name', 'Test Site'],
        ['slogan', 'Test Slogan'],
        ['mail', 'test@example.com'],
      ]);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('system.site')
      ->willReturn($site_config);

    // Cache should be checked first.
    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    // Cache should be set.
    $this->cache->expects($this->once())
      ->method('set');

    // Module handler should allow alterations.
    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('ai_context_collect', $this->anything(), []);

    $result = $this->service->collectContext();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('site', $result);
    $this->assertEquals('Test Site', $result['site']['name']);
  }

}

