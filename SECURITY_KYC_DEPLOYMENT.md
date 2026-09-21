# Déploiement sécurisé KYC et iDenfy

## Architecture retenue

- Les clés API iDenfy et la clé de signature webhook restent exclusivement dans l'API Laravel.
- Le navigateur reçoit uniquement l'URL HTTPS à durée de vie courte créée par iDenfy. Il est redirigé vers un hôte explicitement autorisé.
- Les images du document, la vidéo/selfie et les données biométriques restent chez iDenfy. L'application ne télécharge ni ne journalise ces éléments.
- Le webhook est vérifié par HMAC-SHA256 sur le corps brut avant tout traitement.
- La base locale conserve uniquement les références opaques, le statut, les indicateurs document/visage et les empreintes nécessaires à l'idempotence et à la corrélation.
- Les données KYC applicatives sont chiffrées au repos avec `APP_KEY`. Une copie chiffrée et immuable est créée à la soumission.
- Les PDF et signatures sont stockés dans `storage/app/private/kyc`, jamais sous `public/storage`.

## Variables de production obligatoires

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database

IDENTITY_VERIFICATION_PROVIDER=idenfy
IDENTITY_VERIFICATION_REQUIRED=true
IDENTITY_VERIFICATION_SESSION_LIFETIME=900
IDENTITY_VERIFICATION_SESSION_LENGTH=600
IDENFY_BASE_URL=https://ivs.idenfy.com
IDENFY_API_KEY=...
IDENFY_API_SECRET=...
IDENFY_WEBHOOK_SIGNING_SECRET=...
IDENFY_CALLBACK_URL=https://api.example.com/api/identity-verification/idenfy/webhook
IDENFY_COUNTRY=CM
IDENFY_DOCUMENTS=PASSPORT,ID_CARD,RESIDENCE_PERMIT
IDENFY_ALLOWED_REDIRECT_HOSTS=ivs.idenfy.com,ui.idenfy.com
```

Dans le tableau de bord iDenfy, enregistrer la même URL de callback et activer la signature des callbacks. Ne jamais placer `IDENFY_API_SECRET` ou `IDENFY_WEBHOOK_SIGNING_SECRET` dans Vite, le dépôt Git ou le navigateur.

Demander au support iDenfy la liste actuelle de ses IP sortantes de webhook, puis limiter cette route au pare-feu cPanel/Cloudflare à ces plages. iDenfy ne publie pas ces adresses et elles peuvent changer : la liste blanche IP complète la signature HMAC, elle ne la remplace pas.

## Paiement Stripe Checkout

Le paiement par carte utilise Stripe Checkout hébergé. Le navigateur ne reçoit que l'URL HTTPS temporaire de `checkout.stripe.com`. La navigation de retour ne valide jamais la souscription : l'API recalcule le montant en XAF, vérifie la session auprès de Stripe et traite les webhooks signés de manière idempotente.

```dotenv
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CHECKOUT_TTL_MINUTES=30
STRIPE_CHECKOUT_ALLOWED_HOSTS=checkout.stripe.com
```

Dans Stripe, enregistrer `https://api.example.com/api/stripe/webhook` et écouter au minimum :

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `payment_intent.succeeded`

Utiliser les clés de test en recette et les clés live uniquement en production. Le secret `whsec_...` doit provenir de l'endpoint webhook réellement créé ; une valeur de démonstration ne permet pas de confirmer les paiements.

## Paiement local Mobile Money avec e-nkap

Le choix `Mobile Money` redirige le client vers la page e-nkap hébergée, où il choisit Orange Money ou MTN MoMo selon les opérateurs disponibles. Le montant, la devise XAF et la référence marchand sont créés exclusivement par l'API Laravel.

```dotenv
MAVIANCE_ENABLED=true
ENKAP_CLIENT_ID=...
ENKAP_CLIENT_SECRET=...
ENKAP_BASE_URL=https://api.enkap.cm
ENKAP_CHECKOUT_TTL_MINUTES=30
ENKAP_CHECKOUT_ALLOWED_HOSTS=payment.enkap.cm
```

Dans le portail marchand e-nkap, configurer :

- Return URL : `https://pek.koriassetmanagement.com/payment/return`
- Instant Notification URL en méthode PUT : `https://pek-backoffice.koriassetmanagement.com/api/enkap/webhook`

e-nkap ajoute la référence marchand à la Return URL et à l'URL de notification. Le statut reçu dans cette notification n'est jamais utilisé directement comme preuve : l'API relit la transaction avec son jeton serveur, puis vérifie l'identifiant e-nkap, la référence, le montant et la devise avant de créditer les parts. Les identifiants `ENKAP_CLIENT_*` restent uniquement sur le serveur.

## Ordre de déploiement production

1. Vérifier une sauvegarde restaurable de la base et de `storage/app`.
2. Mettre l'API en maintenance depuis le terminal cPanel : `php artisan down --retry=60`.
3. Déclencher manuellement le workflow GitHub avec la confirmation de maintenance.
4. Depuis le terminal cPanel, exécuter :

```bash
php artisan migrate --force
php artisan kyc:migrate-private-storage --delete-source
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
php artisan up
```

5. Maintenir au moins un worker `php artisan queue:work --tries=3` et le cron `php artisan schedule:run` chaque minute.
6. Vérifier avec un compte de test : création d'une session, redirection iDenfy, webhook signé, statut approuvé, finalisation KYC, validation conformité, création d'une souscription, paiement Stripe, paiement e-nkap en recette et crédit des parts uniquement après confirmation serveur.

Le workflow FTP n'exécute pas les migrations à distance et n'est pas atomique. Il ne doit donc pas être lancé hors maintenance. La meilleure évolution reste un déploiement SSH atomique avec release versionnée, migration et rollback automatisés.

## Gouvernance et confidentialité

- Signer le DPA/contrat de sous-traitance avec le fournisseur, valider ses régions d'hébergement et fixer une durée de conservation minimale.
- Définir une procédure de suppression chez le fournisseur et localement pour les demandes d'effacement, sous réserve des obligations réglementaires de conservation.
- Restreindre l'accès Filament aux seuls rôles conformité autorisés et revoir périodiquement les journaux d'audit.
- Ne jamais écrire dans les logs les payloads KYC, corps de webhook, numéros de pièce, images, jetons ou secrets.
- Effectuer la revue juridique et conformité locale avant activation réelle ; l'implémentation technique ne remplace pas cette validation.

Une vérification SaaS implique nécessairement l'envoi chiffré des pièces et du selfie au fournisseur. Si l'exigence signifie qu'aucune donnée ne doit quitter votre infrastructure, iDenfy hébergé n'est pas compatible : il faut alors sélectionner une offre on-premise ou un prestataire déployé dans votre environnement privé.
