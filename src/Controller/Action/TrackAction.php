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
        ManagerRegistry $managerRegistry,
        // Nullable and last for backwards compatibility: when not injected, bot filtering is skipped.
        // @deprecated will be required in 2.0 — grep "trigger_deprecation" to find shims to drop.
        private readonly ?BotDetectorInterface $botDetector = null,
    ) {
        $this->managerRegistry = $managerRegistry;

        if (null === $botDetector) {
            trigger_deprecation(
                'setono/sylius-conversion-attribution-plugin',
                '1.1',
                'Not passing an instance of "%s" as argument "$botDetector" to "%s()" is deprecated and will be required in 2.0.',
                BotDetectorInterface::class,
                __METHOD__,
            );
        }
    }

    public function __invoke(#[MapRequestPayload] ClientInformation $clientInformation): Response
    {
        // The endpoint is anonymous; drop bot traffic (matched on the real request User-Agent)
        // so automated hits don't flood the source table
        if (null !== $this->botDetector && $this->botDetector->isBotRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $source = $this->sourceFactory->createFromClientInformation($clientInformation);

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
