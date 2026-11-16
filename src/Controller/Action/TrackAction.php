<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\BotDetectionBundle\BotDetector\BotDetectorInterface;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusConversionAttributionPlugin\Factory\SourceFactoryInterface;
use Setono\SyliusConversionAttributionPlugin\Matcher\PageView;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
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

        // The page view normalizes what the browser posted (e.g. a blank referrer is none), so the
        // entity stores the referrer the matchers saw
        $pageView = new PageView($payload->page, $payload->referrer);

        // The tracking script is static (full page cache safe), so everything per-visitor is resolved
        // here. ClientContext reads the client id from the setono_client_id cookie on this (POST) request
        $source = $this->sourceFactory->createNew();
        $source->setClientId($this->clientContext->getClient()->id);
        $source->setPage($payload->page);
        $source->setReferrer($pageView->referrer);
        $source->setIp($request->getClientIp());
        $source->setUserAgent($request->headers->get('user-agent'));
        $source->setSource(SourceInterface::DIRECT);

        $match = $this->sourceMatcher->match($pageView);

        // A link's UTM parameters can be any length or hold any character, and storing one cut short or with a
        // character replaced loses less than dropping the page view
        if (null !== $match) {
            $source->setSource(self::toStorable($match->source));
            $source->setMedium(self::toStorable($match->medium));
            $source->setCampaign(self::toStorable($match->campaign));
        }

        // Nothing else is cut (a client id cut short would be another browser's), so a value too long for its
        // column is a 422, not a 500 on flush
        if (\count($this->validator->validate($source)) > 0) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $manager = $this->getManager($source::class);
        $manager->persist($source);
        $manager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * A utf8mb3 column (Sylius' default charset up to 1.9) can't hold a 4-byte character, e.g. an emoji, and the
     * flush fails. The charset isn't detected, so every database stores them as �
     *
     * The source, medium and campaign columns hold 255 characters, which the Length constraint counts in
     * UTF-8 whatever the internal encoding
     */
    private static function toStorable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        // A value that isn't UTF-8 can only come from a shop's own matcher, and is left for the Length constraint to reject
        $value = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\u{FFFD}", $value) ?? $value;

        return mb_substr($value, 0, 255, 'UTF-8');
    }
}
