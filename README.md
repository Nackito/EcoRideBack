# EcoRide Backend - API Symfony

Backend API REST pour l'application de covoiturage EcoRide, développé avec Symfony 7.

## 🚀 Installation rapide

```bash
# Installer les dépendances
composer install

# Configurer l'environnement
copy .env .env.local

# Créer la base de données
php bin/console doctrine:database:create

# Lancer le serveur de développement
symfony serve
```

L'API sera accessible sur `http://localhost:8000`

## 📋 Prérequis

- PHP 8.1+
- Composer
- MySQL/PostgreSQL
- Symfony CLI (recommandé)

## 🔧 Configuration

### Base de données

Modifiez le fichier `.env.local` :

```env
# MySQL
DATABASE_URL="mysql://username:password@127.0.0.1:3306/ecoride_db"

# PostgreSQL
DATABASE_URL="postgresql://username:password@127.0.0.1:5432/ecoride_db"
```

### Variables d'environnement importantes

```env
APP_ENV=dev
APP_SECRET=your_secret_key
DATABASE_URL=mysql://user:pass@localhost:3306/ecoride_db
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase
```

## 🗃️ Base de données

### Migrations

```bash
# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Voir le statut des migrations
php bin/console doctrine:migrations:status
```

### Fixtures (données de test)

```bash
# Charger les fixtures
php bin/console doctrine:fixtures:load

# Charger en mode append
php bin/console doctrine:fixtures:load --append
```

## 🛡️ Entités principales

### User (Utilisateur)

- id, email, password, firstName, lastName
- roles, isVerified, createdAt, updatedAt

### Ride (Trajet)

- id, origin, destination, departureTime
- availableSeats, price, description
- driver (User), passengers (Collection<User>)

### Booking (Réservation)

- id, user, ride, status, createdAt
- numberOfSeats, message

## 🔌 API Endpoints

### Authentification

```
POST /api/register          # Inscription
POST /api/login             # Connexion
POST /api/logout            # Déconnexion
GET  /api/profile           # Profil utilisateur
```

### Trajets

```
GET    /api/rides           # Lister les trajets
POST   /api/rides           # Créer un trajet
GET    /api/rides/{id}      # Détails d'un trajet
PUT    /api/rides/{id}      # Modifier un trajet
DELETE /api/rides/{id}      # Supprimer un trajet
```

### Réservations

```
GET  /api/bookings          # Mes réservations
POST /api/rides/{id}/book   # Réserver un trajet
PUT  /api/bookings/{id}     # Modifier une réservation
DELETE /api/bookings/{id}   # Annuler une réservation
```

## 🧪 Tests

```bash
# Lancer tous les tests
php bin/phpunit

# Tests avec couverture
php bin/phpunit --coverage-html var/coverage

# Tests spécifiques
php bin/phpunit tests/Entity/UserTest.php
```

## 🔒 Sécurité

### JWT Authentication

```bash
# Générer les clés JWT
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

### CORS Configuration

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
  defaults:
    origin_regex: true
    allow_origin: ["http://localhost:3000"]
    allow_methods: ["GET", "POST", "PUT", "DELETE", "OPTIONS"]
    allow_headers: ["Content-Type", "Authorization"]
    expose_headers: ["Link"]
    max_age: 3600
  paths:
    "^/api/": ~
```

## 📊 Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Voir les routes
php bin/console debug:router

# Voir les services
php bin/console debug:container

# Valider le schema de base
php bin/console doctrine:schema:validate

# Mettre à jour le schema
php bin/console doctrine:schema:update --force
```

## 🚀 Déploiement

### Production

```bash
# Installer les dépendances production
composer install --no-dev --optimize-autoloader

# Vider le cache production
php bin/console cache:clear --env=prod --no-debug

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Optimiser Composer
composer dump-autoload --optimize --no-dev --classmap-authoritative
```

### Variables d'environnement production

```env
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=your_production_secret
DATABASE_URL=mysql://user:pass@host:port/dbname
```

## 📈 Monitoring et Logs

Les logs sont stockés dans `var/log/` :

```bash
# Voir les logs en temps réel
tail -f var/log/dev.log

# Logs d'erreurs
tail -f var/log/prod.log
```

## 🔧 Développement

### Créer une nouvelle entité

```bash
php bin/console make:entity
```

### Créer un contrôleur

```bash
php bin/console make:controller
```

### Créer un formulaire

```bash
php bin/console make:form
```

## 📚 Documentation

- [Symfony Documentation](https://symfony.com/doc)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [API Platform](https://api-platform.com/docs/)

---

Développé avec ❤️ et Symfony
