<?php

declare(strict_types=1);

namespace Setono\SyliusConversionAttributionPlugin\Tests\EventSubscriber;

use Setono\Client\Cookie as ClientCookie;
use Setono\SyliusConversionAttributionPlugin\Model\CustomerInterface;
use Setono\SyliusConversionAttributionPlugin\Tests\Support\PageViewsAndOrders;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AddClientIdOnLoginFunctionalTest extends WebTestCase
{
    use PageViewsAndOrders;

    private const EMAIL = 'ada@example.com';

    private const PASSWORD = 'secret';

    /**
     * @test
     */
    public function it_links_the_browser_at_a_shop_login(): void
    {
        $client = self::createClient();
        self::channel();
        self::shopUser('earlier-browser');

        self::useBrowser($client, 'laptop');
        self::logIn($client);

        self::assertSame(['earlier-browser', 'laptop'], self::storedClientIds());
    }

    /**
     * @test
     */
    public function it_links_the_browser_at_a_remember_me_login(): void
    {
        $client = self::createClient();
        self::channel();
        self::shopUser();
        self::useBrowser($client, 'laptop');
        self::logIn($client, rememberMe: true);

        // The session ends, and the browser comes back with the remember-me cookie and a client id it got since
        $rememberMe = $client->getCookieJar()->get('APP_SHOP_REMEMBER_ME');
        self::assertNotNull($rememberMe);
        $client->restart();
        $client->getCookieJar()->set($rememberMe);
        self::useBrowser($client, 'laptop-later');

        $client->request('GET', '/en_US/account/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSame(['laptop', 'laptop-later'], self::storedClientIds());
    }

    /**
     * @test
     */
    public function it_links_the_browser_when_the_shop_logs_a_new_customer_in_after_registering(): void
    {
        $client = self::createClient();
        self::channel(accountVerificationRequired: false);
        self::useBrowser($client, 'laptop');

        $client->request('GET', '/en_US/register');
        $client->submitForm('Create an account', [
            'sylius_customer_registration[firstName]' => 'Ada',
            'sylius_customer_registration[lastName]' => 'Lovelace',
            'sylius_customer_registration[email]' => self::EMAIL,
            'sylius_customer_registration[user][plainPassword][first]' => self::PASSWORD,
            'sylius_customer_registration[user][plainPassword][second]' => self::PASSWORD,
        ]);

        self::assertResponseRedirects();
        self::assertSame(['laptop'], self::storedClientIds());
    }

    /**
     * @test
     */
    public function it_does_not_link_a_browser_twice(): void
    {
        $client = self::createClient();
        self::channel();
        self::shopUser('laptop');

        self::useBrowser($client, 'laptop');
        self::logIn($client);

        self::assertSame(['laptop'], self::storedClientIds());
    }

    /**
     * @test
     */
    public function it_links_nothing_at_an_api_login(): void
    {
        $client = self::createClient();
        self::channel();
        self::shopUser();

        self::useBrowser($client, 'app');
        $client->jsonRequest('POST', '/api/v2/shop/customers/token', ['email' => self::EMAIL, 'password' => self::PASSWORD]);

        self::assertResponseIsSuccessful();
        self::assertSame([], self::storedClientIds());
    }

    /**
     * @test
     */
    public function it_links_nothing_when_an_admin_logs_in_and_impersonates_a_customer(): void
    {
        $client = self::createClient();
        self::channel();
        self::shopUser();
        self::adminUser();
        self::useBrowser($client, 'office');

        $client->request('GET', '/admin/login');
        $client->submitForm('Login', ['_username' => 'admin', '_password' => self::PASSWORD]);
        self::assertResponseRedirects('http://localhost/admin/');

        $client->request('GET', '/admin/impersonate/' . self::EMAIL);
        self::assertResponseRedirects();

        // The impersonation put the customer in the shop's session, so this request logs no one in
        $client->request('GET', '/en_US/account/dashboard');
        self::assertResponseIsSuccessful();

        self::assertSame([], self::storedClientIds());
    }

    protected static function container(): ContainerInterface
    {
        return self::getContainer();
    }

    private static function logIn(KernelBrowser $client, bool $rememberMe = false): void
    {
        $client->request('GET', '/en_US/login');
        $client->submitForm('Login', ['_username' => self::EMAIL, '_password' => self::PASSWORD, '_remember_me' => $rememberMe]);
        self::assertResponseRedirects('http://localhost/en_US/');
    }

    private static function useBrowser(KernelBrowser $client, string $clientId): void
    {
        $client->getCookieJar()->set(new Cookie('setono_client_id', (string) new ClientCookie($clientId)));
    }

    /**
     * @return list<string>
     */
    private static function storedClientIds(): array
    {
        $entityManager = self::entityManager();
        $entityManager->clear();

        $customer = $entityManager
            ->getRepository(self::modelClass('sylius.model.customer.class', CustomerInterface::class))
            ->findOneBy(['email' => self::EMAIL])
        ;
        self::assertInstanceOf(CustomerInterface::class, $customer);

        return $customer->getClientIds();
    }

    private static function shopUser(string ...$clientIds): void
    {
        $customer = self::customer(...$clientIds);
        $customer->setEmail(self::EMAIL);

        $user = self::factory('sylius.factory.shop_user')->createNew();
        self::assertInstanceOf(ShopUserInterface::class, $user);
        $user->setPlainPassword(self::PASSWORD);
        $user->setEnabled(true);
        $customer->setUser($user);

        $entityManager = self::entityManager();
        $entityManager->persist($customer);
        $entityManager->flush();
    }

    private static function adminUser(): void
    {
        $user = self::factory('sylius.factory.admin_user')->createNew();
        self::assertInstanceOf(AdminUserInterface::class, $user);
        $user->setUsername('admin');
        $user->setEmail('admin@example.com');
        $user->setPlainPassword(self::PASSWORD);
        $user->setLocaleCode('en_US');
        $user->setEnabled(true);

        $entityManager = self::entityManager();
        $entityManager->persist($user);
        $entityManager->flush();
    }

    /**
     * The only channel, so the shop uses it whatever the hostname
     */
    private static function channel(bool $accountVerificationRequired = true): void
    {
        $locale = self::factory('sylius.factory.locale')->createNew();
        self::assertInstanceOf(LocaleInterface::class, $locale);
        $locale->setCode('en_US');

        $currency = self::factory('sylius.factory.currency')->createNew();
        self::assertInstanceOf(CurrencyInterface::class, $currency);
        $currency->setCode('USD');

        $channel = self::factory('sylius.factory.channel')->createNew();
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $channel->setCode('WEB');
        $channel->setName('Web');
        $channel->setDefaultLocale($locale);
        $channel->addLocale($locale);
        $channel->setBaseCurrency($currency);
        $channel->addCurrency($currency);
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->setAccountVerificationRequired($accountVerificationRequired);

        $entityManager = self::entityManager();
        $entityManager->persist($locale);
        $entityManager->persist($currency);
        $entityManager->persist($channel);
        $entityManager->flush();
    }
}
