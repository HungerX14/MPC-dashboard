# MPC Dashboard - Plateforme de gestion WordPress

Plateforme Symfony 8 pour centraliser la gestion de plusieurs sites WordPress connectes via un plugin personnalise.

## Fonctionnalites

- **Authentification** : Systeme de connexion securise avec gestion des sessions
- **Gestion des sites** : CRUD complet pour les sites WordPress connectes
- **Publication d'articles** : Envoi d'articles vers les sites WordPress via API REST
- **Statistiques** : Recuperation des stats depuis les sites (articles, categories, tags)
- **Dashboard** : Vue d'ensemble de tous les sites avec leurs statuts

## Prerequis

- PHP 8.3 ou superieur
- Composer
- PostgreSQL 16 (ou Docker)
- Node.js (optionnel, pour AssetMapper)

## Installation

### Option 1 : Installation avec Docker (recommandee)

```bash
# Cloner le projet
git clone <repository-url>
cd MPC-dashboard

# Copier le fichier d'environnement
cp .env .env.local

# Configurer les variables d'environnement dans .env.local
# DATABASE_URL="postgresql://mpc_user:mpc_secret@database:5432/mpc_dashboard?serverVersion=16&charset=utf8"

# Lancer les containers Docker
docker-compose up -d

# Installer les dependances PHP (dans le container)
docker-compose exec php composer install

# Creer la base de donnees
docker-compose exec php bin/console doctrine:database:create --if-not-exists
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Creer un utilisateur
docker-compose exec php bin/console app:create-user

# L'application est accessible sur http://localhost:8080
# Adminer est accessible sur http://localhost:8081
```

### Option 2 : Installation locale (sans Docker)

```bash
# Cloner le projet
git clone <repository-url>
cd MPC-dashboard

# Installer les dependances
composer install

# Copier et configurer l'environnement
cp .env .env.local
# Modifier DATABASE_URL dans .env.local pour pointer vers votre PostgreSQL local

# Creer la base de donnees
bin/console doctrine:database:create --if-not-exists
bin/console doctrine:migrations:migrate --no-interaction

# Creer un utilisateur
bin/console app:create-user

# Lancer le serveur de developpement
symfony server:start
# ou
php -S localhost:8000 -t public/
```

## Configuration

### Variables d'environnement

```env
# .env.local
APP_ENV=dev
APP_SECRET=your-secret-key-here

# Base de donnees
DATABASE_URL="postgresql://user:password@localhost:5432/mpc_dashboard?serverVersion=16&charset=utf8"

# Pour Docker
DATABASE_URL="postgresql://mpc_user:mpc_secret@database:5432/mpc_dashboard?serverVersion=16&charset=utf8"
```

### Creation d'un utilisateur

```bash
# Mode interactif
bin/console app:create-user

# Mode direct
bin/console app:create-user admin@example.com password123 "Admin User"

# Avec role admin
bin/console app:create-user admin@example.com password123 "Admin User" --admin
```

## Structure du projet

```
src/
├── Command/
│   └── CreateUserCommand.php      # Commande de creation d'utilisateur
├── Controller/
│   ├── DashboardController.php    # Dashboard principal
│   ├── SiteController.php         # CRUD des sites
│   ├── ArticleController.php      # Publication d'articles
│   └── SecurityController.php     # Authentification
├── DTO/
│   ├── ArticleDTO.php             # Donnees article
│   └── StatsDTO.php               # Statistiques
├── Entity/
│   ├── User.php                   # Utilisateur
│   └── Site.php                   # Site WordPress
├── Exception/
│   └── WordpressApiException.php  # Exceptions API
├── Form/
│   ├── SiteType.php               # Formulaire site
│   └── ArticleType.php            # Formulaire article
├── Repository/
│   ├── UserRepository.php
│   └── SiteRepository.php
└── Service/
    ├── WordpressApiClient.php     # Client API WordPress
    ├── SiteManager.php            # Logique metier sites
    └── ArticlePublisher.php       # Publication articles

templates/
├── base.html.twig                 # Layout principal
├── components/
│   ├── _navigation.html.twig      # Navigation
│   └── _flashes.html.twig         # Messages flash
├── dashboard/
│   └── index.html.twig            # Dashboard
├── site/
│   ├── index.html.twig            # Liste des sites
│   ├── create.html.twig           # Creation
│   ├── edit.html.twig             # Edition
│   └── show.html.twig             # Details
├── article/
│   └── create.html.twig           # Creation article
└── security/
    └── login.html.twig            # Connexion
```

## API WordPress (Plugin)

La plateforme communique avec vos sites WordPress via deux endpoints :

### Publication d'article

```
POST {site_url}/wp-json/ma-plateforme/v1/publish
Authorization: Bearer {api_token}
Content-Type: application/json

{
  "title": "Titre de l'article",
  "content": "<p>Contenu HTML</p>",
  "categories": ["Categorie1", "Categorie2"],
  "tags": ["tag1", "tag2"],
  "status": "publish|draft|pending",
  "excerpt": "Resume optionnel"
}
```

### Recuperation des statistiques

```
GET {site_url}/wp-json/ma-plateforme/v1/stats
Authorization: Bearer {api_token}

Response:
{
  "total_posts": 42,
  "total_categories": 5,
  "total_tags": 15,
  "total_pages": 10,
  "site_title": "Mon Site",
  "wordpress_version": "6.4.2"
}
```

## Tests

```bash
# Lancer tous les tests
bin/phpunit

# Tests specifiques
bin/phpunit tests/Controller/SiteControllerTest.php
bin/phpunit tests/Service/WordpressApiClientTest.php
```

## Docker

### Services disponibles

| Service   | Port | Description            |
|-----------|------|------------------------|
| nginx     | 8080 | Serveur web            |
| php       | 9000 | PHP-FPM                |
| database  | 5432 | PostgreSQL             |
| adminer   | 8081 | Interface BDD          |

### Commandes utiles

```bash
# Demarrer les services
docker-compose up -d

# Arreter les services
docker-compose down

# Voir les logs
docker-compose logs -f

# Executer une commande Symfony
docker-compose exec php bin/console <commande>

# Acceder au container PHP
docker-compose exec php sh
```

## Gestion des erreurs API

Le client API gere plusieurs types d'erreurs :

| Code | Type | Description |
|------|------|-------------|
| TIMEOUT | 1 | Le site ne repond pas |
| INVALID_TOKEN | 2 | Token API invalide |
| CONNECTION_ERROR | 3 | Impossible de se connecter |
| INVALID_RESPONSE | 4 | Reponse JSON invalide |
| ENDPOINT_NOT_FOUND | 5 | Endpoint inexistant |
| ACCESS_FORBIDDEN | 6 | Acces refuse |
| SERVER_ERROR | 7 | Erreur serveur WP |

---

## Suggestions pour la version SaaS (Future)

### Multi-tenancy

- Ajouter une relation User -> Sites (chaque user ne voit que ses sites)
- Implementer des organisations/equipes
- Roles granulaires (admin org, editeur, lecteur)

### File d'attente pour publication massive

```yaml
# config/packages/messenger.yaml
messenger:
    transports:
        async: '%env(MESSENGER_TRANSPORT_DSN)%'
    routing:
        'App\Message\PublishArticleMessage': async
```

### Workers

```bash
# Lancer un worker
bin/console messenger:consume async -vv

# Avec Supervisor pour production
[program:messenger]
command=php /var/www/html/bin/console messenger:consume async --time-limit=3600
numprocs=2
```

### Logs centralises

- Integration avec ELK Stack (Elasticsearch, Logstash, Kibana)
- Ou services cloud : Datadog, Sentry, Papertrail

### Quotas et limites

```php
// Exemple d'implementation
class QuotaService
{
    public function canPublish(User $user): bool
    {
        $monthlyLimit = $user->getSubscription()->getMonthlyPublishLimit();
        $published = $this->countPublishedThisMonth($user);
        return $published < $monthlyLimit;
    }
}
```

### API publique

- Implementer API Platform pour exposer une REST/GraphQL API
- OAuth2 pour l'authentification API
- Rate limiting avec Symfony Rate Limiter

### Infrastructure recommandee

- **Load Balancer** : AWS ALB ou Nginx
- **Cache** : Redis pour sessions et cache applicatif
- **CDN** : CloudFlare ou AWS CloudFront
- **Base de donnees** : PostgreSQL avec replicas read
- **Queue** : Redis ou RabbitMQ
- **Monitoring** : Prometheus + Grafana

---

## License

Proprietary - All rights reserved

## Support

Pour toute question, ouvrez une issue sur le repository.
