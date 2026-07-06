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

        $action = new TrackAction($factory, $this->botDetector(false), $registry);

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

        $action = new TrackAction($factory, $this->botDetector(true), $registry);

        $response = $action(new ClientInformation());

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function botDetector(bool $isBot): BotDetectorInterface
    {
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('isBotRequest')->willReturn($isBot);

        return $botDetector;
    }
}
