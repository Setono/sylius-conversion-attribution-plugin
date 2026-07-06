<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\ClientInformation\ClientInformation;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Resolver\ClientInformationResolverInterface;
use Symfony\Component\HttpFoundation\Request;
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
        // Nullable and last for backwards compatibility: when not injected, fields missing from
        // the posted payload are simply not resolved server side.
        // @deprecated will be required in 2.0 — grep "trigger_deprecation" to find shims to drop.
        private readonly ?ClientInformationResolverInterface $clientInformationResolver = null,
    ) {
        $this->managerRegistry = $managerRegistry;

        if (null === $botDetector) {
            trigger_deprecation(
                'setono/sylius-conversion-attribution-plugin',
                '1.2',
                'Not passing an instance of "%s" as argument "$botDetector" to "%s()" is deprecated and will be required in 2.0.',
                BotDetectorInterface::class,
                __METHOD__,
            );
        }

        if (null === $clientInformationResolver) {
            trigger_deprecation(
                'setono/sylius-conversion-attribution-plugin',
                '1.2',
                'Not passing an instance of "%s" as argument "$clientInformationResolver" to "%s()" is deprecated and will be required in 2.0.',
                ClientInformationResolverInterface::class,
                __METHOD__,
            );
        }
    }

    public function __invoke(
        #[MapRequestPayload]
        ClientInformation $clientInformation,
        ?Request $request = null,
    ): Response {
        // The endpoint is anonymous; drop bot traffic (matched on the real request User-Agent)
        // so automated hits don't flood the source table
        if (null !== $this->botDetector && $this->botDetector->isBotRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        // The cache-safe snippet (1.2+) posts only page and referrer; everything per-visitor is
        // resolved here, where the real request carries the setono_client_id cookie. Pages cached
        // before the upgrade keep POSTing full pre-1.2 payloads (clientId, source, ...) for their
        // TTL, so posted values always win and such payloads skip resolution entirely (they always
        // contain both clientId and source).
        if (null !== $this->clientInformationResolver &&
            null !== $request &&
            null !== $clientInformation->page &&
            (null === $clientInformation->clientId || null === $clientInformation->source)
        ) {
            $server = [];

            $ip = $request->getClientIp();
            if (null !== $ip) {
                $server['REMOTE_ADDR'] = $ip;
            }

            $userAgent = $request->headers->get('user-agent');
            if (null !== $userAgent) {
                $server['HTTP_USER_AGENT'] = $userAgent;
            }

            if (null !== $clientInformation->referrer && '' !== $clientInformation->referrer) {
                $server['HTTP_REFERER'] = $clientInformation->referrer;
            }

            try {
                // Reuses the whole existing resolution pipeline: the matcher chain sees the posted
                // page's query parameters and host plus the posted referrer, while the client id is
                // read by ClientContext from the cookie on the CURRENT (POST) request
                $resolved = $this->clientInformationResolver->resolve(
                    Request::create($clientInformation->page, server: $server),
                );

                $clientInformation->clientId ??= $resolved->clientId;
                // Overlay ip/userAgent from the real request, not from $resolved: if the real
                // request lacks them, Request::create()'s defaults (127.0.0.1, "Symfony") must
                // never leak into the database
                $clientInformation->ip ??= $ip;
                $clientInformation->userAgent ??= $userAgent;
                $clientInformation->source ??= $resolved->source;
                $clientInformation->medium ??= $resolved->medium;
                $clientInformation->campaign ??= $resolved->campaign;
            } catch (\Throwable) {
                // an unparsable posted page URL must not turn into a 500 — persist the payload as-is
            }
        }

        $source = $this->sourceFactory->createFromClientInformation($clientInformation);

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
