# Déploiement PEK — configuration koriassetmanagement.com

Configuration active : PWA https://pek-v2.koriassetmanagement.com ; API https://pek-api-v2.koriassetmanagement.com ; backoffice https://pek-api-v2.koriassetmanagement.com/admin ; callback https://pek-api-v2.koriassetmanagement.com/api/s3p/webhook. SESSION_SAME_SITE=lax et SESSION_SECURE_COOKIE=true. Le guide opérationnel à jour est deployment/cpanel-release/LISEZ-MOI.md (dans le workspace parent).

Les sections suivantes conservent le diagnostic historique des anciens domaines ; ne pas utiliser leurs URL pour cette livraison.

## Diagnostic public constaté

La PWA indiquée est `https://pek-v2.e-jabbing.com`, avec un tiret. L'API est
`https://pek-api-v2.ejabbing.com`, sans tiret. Ce sont deux sites différents.
Le 21 septembre, OPTIONS `/api/login` autorise l'origine sans tiret mais pas celle
de la PWA. POST `/api/login` avec un corps vide retourne 422 sal'apns en-tête CORS pour
la PWA : le backend répond, mais le navigateur masque sa réponse.
GET `/api/payment-options` et `/api/v1/payment-options` retournent 404. Le backend
publié ne présente donc pas les routes de cette version, ou utilise un ancien cache
de routes. L'accès HTTP automatisé à la PWA `/login` retourne aussi 403 depuis
l'environnement de vérification ; cela ne suffit pas à déterminer la règle serveur
responsable ni à conclure que tous les navigateurs reçoivent ce refus.

Le cache des routes échouait aussi localement : les deux routes login avaient le
même nom. Les noms de l'API v1 sont désormais préfixés `api.v1.` ; les URL restent
identiques. La commande route:cache a été vérifiée dans un fichier de cache isolé.

## Architecture recommandée

Héberger l'API sous le même domaine principal que la PWA, par exemple
`pek-api-v2.e-jabbing.com`, après configuration DNS, certificat TLS et virtual host.
L'origine reste distincte et doit être autorisée par CORS, mais le cookie n'est
plus un cookie tiers. Utiliser alors `SESSION_SAME_SITE=lax`, mettre à jour
`APP_URL`, `VITE_API_URL`, les URL de callbacks des prestataires et la CSP de
`fcp-mobile/public/.htaccess` pour le nouveau domaine API.

Pour conserver les deux domaines actuels : `SESSION_SAME_SITE=none` et
`SESSION_SECURE_COOKIE=true`. Ce réglage est pris en compte par le cookie
`auth_token`, HttpOnly et limité à l'hôte API. Les navigateurs qui bloquent les
cookies tiers peuvent encore empêcher la session. Ne pas contourner cela en
stockant le jeton dans le JavaScript ou en autorisant toutes les origines.

## Configuration SSH sur l'hébergement actuel

Entrer dans le dossier contenant `artisan` du backend effectivement desservi par
`pek-api-v2.ejabbing.com`. Le document root doit être son dossier `public/`, jamais
la racine Laravel. Préserver le `.env`, l'APP_KEY, la base, les uploads privés et
les fichiers de stockage du serveur. Ne pas copier le `.env` de Windows.

Modifier uniquement les valeurs nécessaires du `.env` existant :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pek-api-v2.ejabbing.com
FRONTEND_URL=https://pek-v2.e-jabbing.com
CORS_ALLOWED_ORIGINS=https://pek-v2.e-jabbing.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
```

Les origines supplémentaires éventuelles doivent être des URL HTTPS exactes,
séparées par des virgules, sans chemin. Le CORS est géré uniquement par Laravel :
publier aussi le nouveau `public/.htaccess` et retirer les anciennes interceptions
OPTIONS ou injections CORS du virtual host/proxy. Exclure `/api/*` du cache CDN,
ne jamais mettre en cache les réponses authentifiées ou les callbacks. Préserver
les en-têtes Origin, Authorization, Idempotency-Key et les en-têtes X-* Maviance.

Après sauvegarde vérifiée de la base, publication du code et installation des
dépendances verrouillées avec PHP 8.3 ou supérieur :

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
php artisan route:list --path=api/payment-options
php artisan route:list --path=api/s3p/webhook
php artisan payments:s3p-check
```

Ne pas lancer `key:generate` sur une application existante : les téléphones et
autres données chiffrées en dépendent. Ne pas utiliser une route web publique
pour exécuter des commandes Artisan. Si PHP-FPM ne revalide pas les timestamps
OPcache, recharger ce pool avec l'outil prévu par l'hébergeur. Purger le cache CDN
après mise à jour, en particulier les anciennes réponses OPTIONS.

Pour la PWA, la construction actuelle utilise
`VITE_API_URL=https://pek-api-v2.ejabbing.com/api`. Exécuter `npm ci` puis
`npm run build` et publier le **contenu de `dist/`**, y compris `.htaccess`, dans
le document root de `pek-v2.e-jabbing.com`. Ne pas publier `src/`, `node_modules/`
ou les fichiers `.env`. Vérifier que `/login` est réécrit sur `index.html`, que
ce fichier est lisible par le serveur et qu'aucune règle de l'hébergeur ne le
refuse. Ne pas appliquer `chmod 777`.

## Vérification sans connexion utilisateur ni encaissement

Depuis un poste disposant de Python 3 et d'un magasin TLS valide :

```sh
python scripts/check-deployment.py --api https://pek-api-v2.ejabbing.com/api --origin https://pek-v2.e-jabbing.com
```

Le script vérifie les OPTIONS de connexion et de souscription (dont
Idempotency-Key), le refus d'une origine non autorisée, la disponibilité de
payment-options et le 401 d'une route protégée sans session. Il ne crée aucun
compte, ne demande aucun OTP et ne déclenche aucun paiement. Il sort avec un code
non nul si le déploiement échoue à ces contrôles. Compléter par une connexion OTP
réelle dans un navigateur et vérifier le maintien de la session après rechargement.

## Maviance avant encaissement réel

L'endpoint exact à enregistrer pour cette API est
`https://pek-api-v2.ejabbing.com/api/s3p/webhook`. `/api/maviance/webhook` correspond
à une autre intégration : ne pas l'utiliser pour S3P Orange/MTN.

- Fournir les identifiants **S3P de production**, les marchands et services
  cashout accordés au compte, le format du numéro et la version API confirmée.
  `S3P_BASE_URL` est la racine HTTPS sans `/v2` ; le code ajoute ce chemin.
- Configurer le secret de callback aussi chez Maviance. Un secret local seul
  ne permet pas au fournisseur de signer correctement les notifications.
- Faire confirmer `S3P_TIMESTAMP_TIMEZONE`. L'ancien exemple de production
  imposait Africa/Douala sans preuve ; il a été corrigé. Le fuseau du fournisseur
  décode le callback ; Africa/Douala reste le fuseau métier de la date de valeur.
- Conserver `S3P_VERIFY_TIMESTAMP_IS_RECEIPT=false` sauf confirmation explicite
  du contrat. Une date de traitement ne prouve pas une date de réception.
- Laisser `S3P_CA_BUNDLE` vide sur un Linux disposant d'un magasin TLS maintenu.
  Ne pas reprendre le chemin Windows ni désactiver TLS.
- Installer la tâche cron `* * * * * cd /chemin/laravel && php artisan schedule:run`
  avec le vrai chemin et la bonne version PHP, et superviser un worker
  `php artisan queue:work`. Les callbacks sont stockés rapidement, puis traités
  par `payments:reconcile`; un cron absent empêche leur traitement automatique.
- Lancer `php artisan payments:s3p-check --network` (lectures uniquement), puis
  une recette autorisée : succès des deux opérateurs, refus, délai réseau,
  notifications répétées, annulation et VL à la date de réception.

Le code vérifie avant crédit PTN, référence, marchand/service, montant exact et
devise. Il ne relance pas collectstd après une transmission incertaine. Il vérifie
la signature sur les octets bruts, stocke les callbacks de manière durable et
vérifie de nouveau la transaction chez Maviance. Les succès de staging ne donnent
aucune part réelle. Une preuve de virement ne confirme jamais à elle seule les fonds.

## Références

- [Maviance, contrat OAuth, collecte et callbacks](https://s3papidoc.smobilpay.maviance.info/)
- [Maviance, callback historique](https://apidocs.smobilpay.com/s3papi/Callback-support-via-Webhook.1578338315.html)
- [MDN, CORS et cookies tiers](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CORS)


## Paiements mobiles automatiques

Le webhook persiste sa notification avant réponse puis déclenche son traitement après réponse HTTP.
Avec PHP-FPM/FastCGI, cela permet une confirmation rapide sans worker dédié pour cette étape.
La tâche `payments:reconcile` planifiée chaque minute reste indispensable pour récupérer
les interruptions, les notifications différées, les dates et les VL manquantes.
Configurer dans cPanel une entrée cron `* * * * * cd /CHEMIN/ABSOLU/LARAVEL && /CHEMIN/PHP artisan schedule:run`.
Vérifier `php artisan schedule:list` et exécuter une fois `php artisan payments:reconcile`.
Superviser les erreurs cron ; ne pas désactiver le rapprochement après un test réussi.
La PWA consulte uniquement PEK toutes les dix secondes quand sa page est visible.

Dans une base contenant exclusivement des tests, `APP_ENV=staging` et
`S3P_CREDIT_TEST_PARTS=true` autorisent les parts de recette avec l’hôte S3P staging.
Cette autorisation ne s’applique jamais en production. Le mode simulation reste désactivé.
Le fuseau du timestamp doit correspondre à celui confirmé par Maviance.
Ne pas activer `S3P_VERIFY_TIMESTAMP_IS_RECEIPT` sans confirmation de sa signification :
la date du callback SUCCESS authentifié est utilisée en priorité.
Les anciennes confirmations `staging_only` ayant un callback daté peuvent être retraitées
par `payments:reconcile` après activation explicite du crédit de recette.


## Convention de date de recette (23 septembre 2026)

Sur une base exclusivement de test : APP_ENV=staging, S3P_CREDIT_TEST_PARTS=true,
S3P_STAGING_USE_PROVIDER_TIMESTAMP=true, MOBILE_MONEY_SIMULATION=false.
Conserver S3P_VERIFY_TIMESTAMP_IS_RECEIPT=false tant que sa signification réelle
n’a pas été confirmée par Maviance. La nouvelle convention est limitée au staging
et exige aussi un contexte de transaction et un endpoint Maviance staging.

Après déploiement : `php artisan config:cache`, puis `php artisan payments:reconcile`.
Le rapprochement reconsulte les paiements en succès bloqués sur leur date, sans collectstd.
Une vérification effectuée il y a moins d’une minute sera reprise au prochain passage.

Le timestamp validé est stocké en UTC dans funds_received_at ; la date de valeur
est calculée dans payments.timezone (Africa/Douala). Le contexte s3p_context
conserve receipt_timestamp_original, receipt_timestamp_utc et receipt_timestamp_source
(verifytx_staging ou callback). Aucun fallback à l’heure actuelle ni à la VL courante.
Un callback contradictoire produit un événement mobile_payment_date_conflict et une
notification ; les parts attribuées sont préservées. Si la valorisation n’avait pas
encore eu lieu, elle est bloquée en payment_date_conflict pour rapprochement.
La convention de recette ne prouve pas la réception des callbacks ni la signification
de verifytx.timestamp en production.

## Correction VL antérieure — 23 septembre 2026

Cette règle remplace la sélection de VL à date exacte décrite plus haut. La date de valeur reste la date de réception des fonds, dans le fuseau du fonds. La valorisation sélectionne la VL du même produit dont la date est strictement antérieure à la date de valeur, par date décroissante. Une VL du même jour ou postérieure est exclue. Sans VL antérieure, la souscription reste en attente. Les contrôles de validation client, paiement et staging sont conservés.

La date de VL appliquée est figée dans `subscriptions.nav_date`, distincte de `value_date`, et affichée dans le PWA. Les souscriptions déjà valorisées ne sont pas recalculées.

Installation : extraire le correctif backend à la racine Laravel, puis exécuter `php artisan migrate --force`, `php artisan config:cache` et `php artisan payments:reconcile`. La nouvelle migration ajoute seulement une colonne nullable. Déployer aussi le PWA reconstruit. Aucun nouveau débit n'est nécessaire.
