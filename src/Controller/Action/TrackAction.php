<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class TrackAction
{
    use ORMTrait;

    public function __construct(
        private readonly SourceFactoryInterface $sourceFactory,
        private readonly BotDetectorInterface $botDetector,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(#[MapRequestPayload] ClientInformation $clientInformation): Response
    {
        // The endpoint is anonymous; drop bot traffic (matched on the real request User-Agent)
        // so automated hits don't flood the source table
        if ($this->botDetector->isBotRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $source = $this->sourceFactory->createFromClientInformation($clientInformation);

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
