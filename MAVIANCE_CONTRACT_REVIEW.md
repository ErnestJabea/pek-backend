# Revue du contrat Maviance S3P pour PEK

Cette revue concerne les encaissements Orange Money/MTN, pas les décaissements. La collection « S3P API Staging V2.0.0 » fournie utilise la famille d'URL `/v2` et un exemple d'en-tête `x-api-version: 3.0.0`. Le portail actuel annonce 3.2.0. Ces numéros ne sont pas interchangeables : PEK conserve une version configurable, à confirmer avec l'accès marchand.

## Références et décisions

| Référence primaire | Élément vérifié | Décision PEK |
|---|---|---|
| [Portail développeur Maviance](https://s3papidoc.smobilpay.maviance.info/) | OAuth client credentials, encaissement cashout, limites de consultation | Identifiants côté serveur ; TLS obligatoire ; jeton mis en cache ; cadence minimale de dix secondes ; pas de consultation automatique permanente après succès |
| [CollectionRequest du SDK Maviance](https://raw.githubusercontent.com/maviance/smobilpay-php/master/docs/Model/CollectionRequest.md) | `trid` n'a pas de garantie d'unicité côté fournisseur ; téléphone de conformité international | Référence unique en base et réservation avant débit ; aucun retry de collectstd ; format serviceNumber distinct et configurable |
| [Cashout du SDK Maviance](https://raw.githubusercontent.com/maviance/smobilpay-php/master/docs/Model/Cashout.md) | Marchand, service, devise et type de montant | Vérification de l'unique produit retourné ; refus d'un produit FIXED pour une souscription à montant libre |
| [Quote du SDK Maviance](https://raw.githubusercontent.com/maviance/smobilpay-php/master/docs/Model/Quote.md) | Produit, prix, montant du service et expiration | Montant/produit contrôlés ; expiration vérifiée avant collecte ; aucun supplément opérateur accepté silencieusement |
| [PaymentStatus du SDK Maviance](https://raw.githubusercontent.com/maviance/smobilpay-php/master/docs/Model/PaymentStatus.md) | PTN unique ; receiptNumber non unique ; timestamp de traitement ; payItemId optionnel | PTN comme identité fournisseur ; reçu pour rapprochement seulement ; date de traitement non assimilée par défaut à la réception ; comparaison payItemId s'il est présent |
| [CollectionResponse du SDK Maviance](https://raw.githubusercontent.com/maviance/smobilpay-php/master/docs/Model/CollectionResponse.md) | Champs retournés après exécution | PTN conservé ; contrôles des champs de corrélation et de montant présents ; réponse d'exécution seule insuffisante pour créditer |
| [Callbacks officiels](https://apidocs.smobilpay.com/s3papi/Callback-support-via-Webhook.1578338315.html) | Signature et horodatage d'entrée dans l'état final | Contrôle du corps brut, stockage durable puis vérification serveur ; fuseau historique à faire confirmer |

La collection contient des exemples d'autres services (cashin, factures, produits, recharges) et des données d'exemple. Ils ne constituent ni des instructions à exécuter ni des valeurs à copier dans PEK. Le catalogue autorisé au compte marchand doit déterminer les services réels.

## Invariants de l'intégration

- Un client validé KYC fournit son investissement ; PEK calcule le total et fige les frais.
- Le portefeuille est normalisé et chiffré en base. Aucune saisie du PIN opérateur dans PEK.
- Le devis fige environnement, empreinte du compte, marchand, service, produit, total et format du portefeuille. Changer la configuration ne réaffecte pas une transaction ancienne à un autre compte.
- Les montants sont comparés en décimal exact ; chaînes scientifiques, booléens, fractions inattendues et devises différentes sont refusés.
- Une erreur réseau après transmission reste incertaine : vérifier la référence existante, jamais recréer automatiquement un débit.
- Seule une vérification fournisseur cohérente permet le succès. Le callback ne vaut pas à lui seul confirmation de montant.
- Les états terminaux ne régressent pas sous l'effet d'une réponse plus ancienne. Une annulation interdit tout crédit ultérieur automatique.
- La date de réception et sa VL conditionnent l'attribution. La date du contrôle n'est jamais substituée.

## Exploitation et contrôle de staging

Le défaut de confiance TLS de PHP/MAMP a été corrigé localement avec les autorités racines approuvées par Windows, exportées dans un fichier privé et référencées par `S3P_CA_BUNDLE`. La vérification du certificat et du nom d'hôte reste active. Le fichier d'autorités ne contient aucune clé privée et est exclu de Git. Sur le serveur de production, utiliser son magasin de confiance maintenu, pas ce fichier de développement.

Les accès staging fournis dans la collection ont permis OAuth HTTP 200, `/ping` HTTP 200, lecture du catalogue et devis via le véritable adaptateur PHP. Ils sont configurés uniquement dans le `.env` local ignoré par Git. `S3P_ENABLED=true` active désormais les essais interactifs locaux Orange et MTN, avec une mention explicite du mode test dans l'interface ; la simulation reste désactivée. Un succès de staging sur une base persistante reçoit `valuation_status=staging_only` et ne peut pas créditer de parts réelles, même après un callback. Le service refuse de proposer le staging en environnement de production. Un secret de webhook local a été généré mais son enregistrement chez Maviance n'est pas confirmé. La commande de diagnostic réseau peut lire le catalogue sans activer les encaissements.

Le 17 septembre 2026, avec les deux numéros de staging fournis par l'utilisateur, une seule collecte par opérateur a été envoyée dans une base SQLite isolée, via le contrôleur et les services PEK. Aucun utilisateur ou portefeuille client de la base principale n'a été utilisé ; les files de reçus et mails étaient neutralisées dans la recette.

| Opérateur | Marchand / service | Total | PTN | Résultat confirmé par verifytx |
|---|---|---|---|---|
| Orange Money | CMORANGEOM / 300215 | 75 750 XAF | 99999178962381900079982890865590 | SUCCESS |
| MTN Mobile Money | MTNMOMO / 20053 | 75 750 XAF | 99999178962382500065657040989968 | SUCCESS |

Les deux transactions ont passé les contrôles d'identité, marchand, service, produit, montant et devises. Le numéro de reçu a été conservé. Les constats réels ont nécessité d'accepter `errorCode: null` et `veriCode: ""`, normalisé en null, sans relâcher les contrôles financiers. Les réponses verifytx sont des listes d'un élément. Le validateur refuse toujours un succès accompagné d'un code d'erreur explicite ou une liste ambiguë.

L'horodatage retourné par verifytx reste celui déjà présent à l'état PENDING : il ne prouve donc pas à lui seul l'heure de réception définitive. Les deux souscriptions de recette sont `mobile_state=success`, `valuation_status=awaiting_payment_date`, et ne créditent aucune part. La réception d'un callback signé depuis Maviance n'a pas été testée : une URL HTTPS publique de PEK et l'enregistrement du secret chez Maviance restent nécessaires.

Preuves privées locales, sans jetons ni clés dans les rapports : `storage/app/private/s3p-staging-check.json`, `s3p-staging-init.json`, `s3p-staging-status.json`. Le script `s3p-staging-smoke.php`, sans argument, consulte uniquement les références existantes ; ne pas effacer sa base isolée puis répéter `--init`, ce qui créerait de nouvelles demandes. Les numéros complets et la base de recette restent hors Git.

Faire confirmer pour le compte destiné à la production : marchand et service de chaque opérateur, format de serviceNumber, éventuels frais fournisseur, champs et fuseau des dates, URL et secret de callback. Le staging a accepté le format international pour les deux numéros fournis et des devis sans supplément par rapport aux 75 750 XAF demandés. Cela ne prouve pas que les mêmes conditions s'appliqueront en production. Les contrôles réseau commencent par `php artisan payments:s3p-check --network`.

Recette restant à exécuter chez le fournisseur avant activation : refus, solde insuffisant, timeout de collectstd, callback doublé, callback arrivé pendant un poll, annulation et réception du callback daté. Ces scénarios et les divergences de montant/service sont couverts localement par des réponses contrôlées ; cela ne remplace pas leur recette fournisseur. Tester aussi l'arrêt puis le redémarrage du planificateur pour prouver la reprise en exploitation.

Pour activer réellement, le planificateur Laravel doit exécuter `payments:reconcile` chaque minute. Les callbacks en attente doivent être supervisés, ainsi que les divergences et les succès sans date exploitable. Aucun test visuel ni test de charge MySQL concurrent n'est revendiqué par cette revue.

## Résultats de validation finale

- Suite ciblée : 89 tests réussis, 362 assertions (contrat S3P, workflow sécurisé, souscriptions, webhooks, e-nkap, produits et portefeuille).
- Build Vue réussi ; avertissement préexistant sur la taille des bundles.
- Migration S3P exécutée localement après sauvegarde SQLite cohérente.
- Rapports de recette : deux appels collectstd, un par opérateur ; les deux références sont absentes de la base principale.
- Contrôle HTTP local après activation de recette : l'API directe et le relais Vite renvoient Orange et MTN disponibles, avec `s3p_mode=staging`. La recette du callback reste nécessaire avant activation commerciale.
- `.env`, le magasin d'autorités local, la base isolée et le script de recette sont ignorés par Git.
