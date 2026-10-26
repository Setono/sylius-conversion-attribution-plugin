<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
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
        private readonly ClientInformationResolverInterface $clientInformationResolver,
        private readonly ValidatorInterface $validator,
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

        $server = [];
        if (null !== $referrer) {
            $server['HTTP_REFERER'] = $referrer;
        }

        // The tracking script is static (full page cache safe), so everything per-visitor is resolved
        // here: the matcher chain sees the posted page's query parameters and host plus the posted
        // referrer through a synthetic request, while ClientContext reads the client id from the
        // setono_client_id cookie on the CURRENT (POST) request
        $clientInformation = $this->clientInformationResolver->resolve(Request::create($payload->page, server: $server));

        // The synthetic request only exists for the matchers. Take page and referrer from the payload
        // (Request::getUri() would normalize the query string) and ip/user agent from the real request,
        // so Request::create()'s defaults (127.0.0.1, "Symfony") never reach the database
        $clientInformation->page = $payload->page;
        $clientInformation->referrer = $referrer;
        $clientInformation->ip = $request->getClientIp();
        $clientInformation->userAgent = $request->headers->get('user-agent');

        // Resolved values come from the visitor (cookie, utm_* parameters, referrer host), so they must
        // fit the columns: an over-long value is a 422, not a 500 on flush
        if (\count($this->validator->validate($clientInformation)) > 0) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $source = $this->sourceFactory->createFromClientInformation($clientInformation);

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
