<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Setono\Client\Client;
use Setono\ClientBundle\Context\ClientContextInterface;
use Setono\SyliusConversionAttributionPlugin\EventSubscriber\AddClientIdOnLoginSubscriber;
use Setono\SyliusConversionAttributionPlugin\Tests\Application\Model\Customer;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Bundle\UserBundle\Event\UserEvent;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\Customer as BaseCustomer;
use Sylius\Component\Core\Model\ShopUser;

/**
 * The login flows are tested through the test application in AddClientIdOnLoginFunctionalTest. These are what its
 * compiled container and models can't show: the events subscribed to, how a link is saved, and users and customers
 * the test application doesn't have
 */
final class AddClientIdOnLoginSubscriberTest extends TestCase
{
    /**
     * @test
     */
    public function it_subscribes_to_shop_logins_but_not_to_impersonation(): void
    {
        self::assertSame([
            'security.interactive_login' => 'onInteractiveLogin',
            'sylius.user.security.implicit_login' => 'onImplicitLogin',
        ], AddClientIdOnLoginSubscriber::getSubscribedEvents());
    }

    /**
     * @test
     */
    public function it_saves_the_customer_with_the_browser_linked(): void
    {
        $customer = new Customer();
        $customer->addClientId('phone');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($customer);
        $entityManager->expects(self::once())->method('flush');
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(Customer::class)->willReturn($entityManager);

        $this->createSubscriber('laptop', $managerRegistry)->onImplicitLogin(new UserEvent(self::shopUser($customer)));

        self::assertSame(['phone', 'laptop'], $customer->getClientIds());
    }

    /**
     * @test
     */
    public function it_saves_nothing_when_the_browser_is_already_linked(): void
    {
        $customer = new Customer();
        $customer->addClientId('laptop');

        $this->createSubscriber('laptop')->onImplicitLogin(new UserEvent(self::shopUser($customer)));

        self::assertSame(['laptop'], $customer->getClientIds());
    }

    /**
     * @test
     */
    public function it_ignores_a_user_that_is_not_a_shop_user(): void
    {
        $this->createSubscriber('laptop')->onImplicitLogin(new UserEvent(new AdminUser()));
    }

    /**
     * @test
     */
    public function it_leaves_a_customer_without_the_plugin_interface_alone(): void
    {
        $this->createSubscriber('laptop')->onImplicitLogin(new UserEvent(self::shopUser(new BaseCustomer())));
    }

    private static function shopUser(BaseCustomer $customer): ShopUser
    {
        $user = new ShopUser();
        $user->setCustomer($customer);

        return $user;
    }

    /**
     * Without a manager registry, the subscriber must not save anything
     */
    private function createSubscriber(string $clientId, ?ManagerRegistry $managerRegistry = null): AddClientIdOnLoginSubscriber
    {
        $clientContext = $this->createMock(ClientContextInterface::class);
        $clientContext->method('getClient')->willReturn(new Client($clientId));

        $sectionProvider = $this->createMock(SectionProviderInterface::class);
        $sectionProvider->method('getSection')->willReturn(new ShopSection());

        if (null === $managerRegistry) {
            $managerRegistry = $this->createMock(ManagerRegistry::class);
            $managerRegistry->expects(self::never())->method('getManagerForClass');
        }

        return new AddClientIdOnLoginSubscriber($clientContext, $sectionProvider, $managerRegistry);
    }
}
