<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Factory;

use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\Matcher\SourceMatcherInterface;
use Setono\SyliusConversionAttributionPlugin\Model\SourceInterface;
use Setono\SyliusConversionAttributionPlugin\Parser\ReferrerParserInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class SourceFactory implements SourceFactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $decorated,
        private readonly ClientContextInterface $clientContext,
        private readonly ReferrerParserInterface $referrerParser,
        private readonly SourceMatcherInterface $sourceMatcher,
    ) {
    }

    public function createNew(): SourceInterface
    {
        /** @var SourceInterface|object $obj */
        $obj = $this->decorated->createNew();
        Assert::isInstanceOf($obj, SourceInterface::class);

        $obj->setClientId($this->clientContext->getClient()->id);

        return $obj;
    }

    public function createFromRequest(Request $request): SourceInterface
    {
        $obj = $this->createNew();

        $referrer = $request->headers->get('referer');
        $obj->setReferrer($referrer);

        if (null !== $referrer) {
            $parsedReferrer = $this->referrerParser->parse($referrer);
            $obj->setSource($parsedReferrer->source);
            $obj->setMedium($parsedReferrer->medium);
        }

        $source = $this->sourceMatcher->match($request);
        if (null !== $source) {
            $obj->setSource($source->source);

            if (null !== $source->medium) {
                $obj->setMedium($source->medium);
            }
        }

        return $obj;
    }
}
