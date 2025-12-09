<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Site;
use App\Entity\User;
use App\Repository\SiteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SiteControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $testUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Create test user
        $this->testUser = $this->createTestUser($container);

        // Login
        $this->client->loginUser($this->testUser);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\Site')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();

        parent::tearDown();
    }

    private function createTestUser($container): User
    {
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFullName('Test User');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function testSiteIndexPage(): void
    {
        $this->client->request('GET', '/sites');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sites WordPress');
    }

    public function testCreateSitePage(): void
    {
        $this->client->request('GET', '/sites/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Ajouter un site WordPress');
    }

    public function testCreateSiteFormSubmission(): void
    {
        $crawler = $this->client->request('GET', '/sites/new');

        $form = $crawler->selectButton('Ajouter le site')->form([
            'site[name]' => 'Mon Site Test',
            'site[url]' => 'https://example.com',
            'site[apiToken]' => 'test-token-123456789',
        ]);

        $this->client->submit($form);

        // Should redirect to site show page
        $this->assertResponseRedirects();

        // Follow redirect and check success message
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Verify site was created in database
        /** @var SiteRepository $siteRepository */
        $siteRepository = static::getContainer()->get(SiteRepository::class);
        $site = $siteRepository->findOneBy(['name' => 'Mon Site Test']);

        $this->assertNotNull($site);
        $this->assertEquals('https://example.com', $site->getUrl());
        $this->assertEquals('test-token-123456789', $site->getApiToken());
    }

    public function testCreateSiteValidation(): void
    {
        $crawler = $this->client->request('GET', '/sites/new');

        // Submit with empty fields
        $form = $crawler->selectButton('Ajouter le site')->form([
            'site[name]' => '',
            'site[url]' => '',
            'site[apiToken]' => '',
        ]);

        $this->client->submit($form);

        // Should stay on same page with validation errors
        $this->assertResponseIsSuccessful();
    }

    public function testShowSitePage(): void
    {
        // Create a site first
        $site = new Site();
        $site->setName('Test Site');
        $site->setUrl('https://test-site.com');
        $site->setApiToken('token123');

        $this->entityManager->persist($site);
        $this->entityManager->flush();

        $this->client->request('GET', '/sites/' . $site->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Test Site');
    }

    public function testEditSitePage(): void
    {
        // Create a site first
        $site = new Site();
        $site->setName('Test Site Edit');
        $site->setUrl('https://edit-site.com');
        $site->setApiToken('token456');

        $this->entityManager->persist($site);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/sites/' . $site->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Modifier le site');

        // Update the site
        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'site[name]' => 'Updated Site Name',
            'site[url]' => 'https://updated-site.com',
            'site[apiToken]' => 'updated-token',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects();

        // Verify update
        $this->entityManager->clear();
        /** @var SiteRepository $siteRepository */
        $siteRepository = static::getContainer()->get(SiteRepository::class);
        $updatedSite = $siteRepository->find($site->getId());

        $this->assertEquals('Updated Site Name', $updatedSite->getName());
        $this->assertEquals('https://updated-site.com', $updatedSite->getUrl());
    }

    public function testDeleteSite(): void
    {
        // Create a site first
        $site = new Site();
        $site->setName('Site To Delete');
        $site->setUrl('https://delete-me.com');
        $site->setApiToken('token789');

        $this->entityManager->persist($site);
        $this->entityManager->flush();

        $siteId = $site->getId();

        // Delete the site
        $this->client->request('POST', '/sites/' . $siteId, [
            '_token' => static::getContainer()->get('security.csrf.token_manager')->getToken('delete' . $siteId)->getValue(),
        ]);

        $this->assertResponseRedirects('/sites');

        // Verify deletion
        $this->entityManager->clear();
        /** @var SiteRepository $siteRepository */
        $siteRepository = static::getContainer()->get(SiteRepository::class);
        $deletedSite = $siteRepository->find($siteId);

        $this->assertNull($deletedSite);
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        // Create new client without login
        $client = static::createClient();
        $client->request('GET', '/sites');

        $this->assertResponseRedirects('/login');
    }
}
