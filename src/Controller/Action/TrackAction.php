<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TrackAction
{
    use ORMTrait;

    public function __construct(
        private readonly SourceFactoryInterface $sourceFactory,
        ManagerRegistry $managerRegistry,
        private readonly BotDetectorInterface $botDetector,
        private readonly ClientContextInterface $clientContext,
        private readonly SourceMatcherInterface $sourceMatcher,
        private readonly ValidatorInterface $validator,
        private readonly string $defaultSource,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(#[MapRequestPayload] TrackPayload $payload, Request $request): Response
    {
        // The endpoint is anonymous; drop bot traffic (matched on the real request User-Agent)
        // so automated hits don't flood the source table
        if ($this->botDetector->isBotRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $referrer = null === $payload->referrer || '' === $payload->referrer ? null : $payload->referrer;

        // The tracking script is static (full page cache safe), so everything per-visitor is resolved
        // here. ClientContext reads the client id from the setono_client_id cookie on this (POST) request
        $source = $this->sourceFactory->createNew();
        $source->setClientId($this->clientContext->getClient()->id);
        $source->setPage($payload->page);
        $source->setReferrer($referrer);
        $source->setIp($request->getClientIp());
        $source->setUserAgent($request->headers->get('user-agent'));
        $source->setSource($this->defaultSource);

        // The matchers inspect the query parameters, host and Referer header of the page view, so
        // they get a synthetic request built from the posted page and referrer
        $match = $this->sourceMatcher->match(Request::create(
            $payload->page,
            server: null === $referrer ? [] : ['HTTP_REFERER' => $referrer],
        ));

        if (null !== $match) {
            $source->setSource($match->source);
            $source->setMedium($match->medium);
            $source->setCampaign($match->campaign);
        }

        // An over-long value (e.g. a utm_campaign longer than the column) is a 422, not a 500 on flush
        if (\count($this->validator->validate($source)) > 0) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
