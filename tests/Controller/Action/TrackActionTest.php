<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Controller\Action\TrackAction;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Model\Source;
use Symfony\Component\HttpFoundation\Response;

final class TrackActionTest extends TestCase
{
    /**
     * @test
     */
    public function it_persists_a_source_for_a_regular_request(): void
    {
        $source = new Source();

        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->method('createFromClientInformation')->willReturn($source);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with($source);
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $action = new TrackAction($factory, $registry, $this->botDetector(false));

        $response = $action(new ClientInformation());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_ignores_bot_requests_without_persisting(): void
    {
        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->expects(self::never())->method('createFromClientInformation');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        $action = new TrackAction($factory, $registry, $this->botDetector(true));

        $response = $action(new ClientInformation());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @test
     *
     * @group legacy
     */
    public function it_persists_and_warns_without_a_bot_detector_for_backwards_compatibility(): void
    {
        $source = new Source();

        $factory = $this->createMock(SourceFactoryInterface::class);
        $factory->method('createFromClientInformation')->willReturn($source);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with($source);
        $manager->expects(self::once())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $response = null;

        // Legacy two-argument construction (no bot detector) must still work and warn
        $deprecations = $this->captureDeprecations(function () use ($factory, $registry, &$response): void {
            $action = new TrackAction($factory, $registry);
            $response = $action(new ClientInformation());
        });

        self::assertNotEmpty($deprecations);
        self::assertStringContainsString('$botDetector', $deprecations[0]);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function botDetector(bool $isBot): BotDetectorInterface
    {
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('isBotRequest')->willReturn($isBot);

        return $botDetector;
    }

    /**
     * Runs $callback with a temporary handler that records E_USER_DEPRECATED messages.
     *
     * @return list<string>
     */
    private function captureDeprecations(callable $callback): array
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $callback();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
