<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
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
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(#[MapRequestPayload] ClientInformation $clientInformation): Response
    {
        $source = $this->sourceFactory->createFromClientInformation($clientInformation);

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
