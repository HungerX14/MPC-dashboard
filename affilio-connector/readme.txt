=== Affilio Connector ===
Contributors: affilio
Tags: affiliation, publishing, api, automation
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connecte votre site WordPress a la plateforme Affilio pour la gestion centralisee de vos publications.

== Description ==

Affilio Connector permet de connecter votre site WordPress a la plateforme Affilio.

Fonctionnalites :

* Publication d'articles depuis Affilio
* Statistiques du site en temps reel
* Gestion automatique des categories et tags
* API REST securisee avec authentification Bearer token
* Interface d'administration intuitive

== Installation ==

1. Telechargez le dossier `affilio-connector`
2. Uploadez-le dans `/wp-content/plugins/`
3. Activez le plugin dans le menu 'Extensions'
4. Allez dans Reglages > Affilio pour configurer le plugin
5. Copiez l'URL du site et le Token API dans votre plateforme Affilio

== Configuration dans Affilio ==

1. Dans Affilio, ajoutez un nouveau site
2. Collez l'URL de votre site WordPress (ex: https://monsite.com)
3. Collez le Token API genere par le plugin
4. Testez la connexion

== Endpoints API ==

Le plugin expose les endpoints suivants :

* GET /wp-json/ma-plateforme/v1/stats - Statistiques du site
* POST /wp-json/ma-plateforme/v1/publish - Publier un article
* GET /wp-json/ma-plateforme/v1/health - Verification de sante
* GET /wp-json/ma-plateforme/v1/categories - Liste des categories
* GET /wp-json/ma-plateforme/v1/tags - Liste des tags

== Securite ==

* Authentification par Bearer Token
* Tokens generes cryptographiquement
* Possibilite de regenerer le token a tout moment

== Changelog ==

= 1.0.0 =
* Version initiale
