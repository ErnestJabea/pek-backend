# Paiements PEK — fonctionnement et activation

État du 17 septembre 2026. Implémentation locale ; les deux encaissements de recette sur le véritable staging Maviance ont atteint SUCCESS. Orange et MTN sont désormais disponibles dans l'interface locale avec une mention du mode test. Tout succès interactif de staging reste `staging_only` sans crédit de parts réelles, y compris après callback ; le staging est interdit en environnement de production. L'encaissement réel exige les accès de production et la validation du callback public et de la date de réception. Aucune mise en production n'est certifiée par ce document. Voir `MAVIANCE_CONTRACT_REVIEW.md` pour les preuves et limites.

## Montant, frais et parts

Le client choisit `investment_amount`, entier XAF. Le serveur calcule les frais avec `payments.fee_basis_points` (100 = 1 %) et ignore les totaux fournis par le navigateur. Exemple : 75 000 investis + 750 de frais = 75 750 à encaisser. Les parts affichées à la demande sont indicatives pour ces moyens de paiement.

La date de valeur est la date de réception des fonds, en Africa/Douala. Seule la VL publiée pour cette date est utilisée. Pas de substitution par la VL de la demande, celle du jour de contrôle, ni la dernière VL disponible. Sans cette VL, les fonds sont enregistrés mais la souscription reste en attente et exclue du portefeuille. L'arrondi des parts se fait vers le bas, à huit décimales.

## Virements

1. Le client choisit un compte actif. Le serveur conserve un instantané du compte et du bénéficiaire ; une modification ultérieure des coordonnées ne change pas la demande.
2. Le client vire le total en mentionnant la référence de souscription, puis dépose éventuellement un justificatif PDF/JPEG/PNG (10 Mo maximum, 10 documents par souscription).
3. Le justificatif reste une déclaration du client, jamais une confirmation de fonds. Un utilisateur disposant de `review_payment_proof` peut examiner un document sain ou demander un remplacement.
4. La comptabilité, avec `confirm_bank_payment`, vérifie le crédit sur le relevé de PEK puis saisit sa date effective, le montant reçu et la référence bancaire. Les écarts de montant sont refusés et nécessitent une résolution séparée. Une même opération bancaire ne peut financer deux demandes.
5. Les parts et le reçu sont générés après rapprochement ET disponibilité de la VL de réception. Une confirmation répétée n'émet pas un second reçu.

Les anciennes demandes sans instantané exigent de renseigner le compte lors du rapprochement. L'édition générique du backoffice ne peut plus changer les montants, les parts ou le statut : seules les notes internes sont modifiables. La création/suppression manuelle des souscriptions est bloquée pour éviter de contourner le processus financier.

## Orange Money et MTN Mobile Money

La passerelle S3P est distincte de l'ancienne intégration e-nkap. Utiliser des identifiants S3P dédiés fournis au marchand, jamais les jetons exportés dans Postman.

Configurer `S3P_ENABLED`, `S3P_BASE_URL`, `S3P_ALLOWED_HOSTS`, `S3P_PUBLIC_KEY`, `S3P_SECRET_KEY`, `S3P_WEBHOOK_SECRET`, `S3P_API_VERSION`, `S3P_ORANGE_SERVICE_ID` et `S3P_MTN_SERVICE_ID`. Les identifiants de service ne sont pas devinés. Faire confirmer par Maviance les services d'encaissement, devis, montant total et devise. Le contrat de réponse doit être validé en staging avant activation commerciale.

Configurer également `S3P_ORANGE_MERCHANT` et `S3P_MTN_MERCHANT` à partir du catalogue autorisé. `S3P_ORANGE_WALLET_FORMAT` et `S3P_MTN_WALLET_FORMAT` acceptent `international` ou `national` selon le contrat de chaque service ; `customerPhonenumber` reste au format international. La collection Postman contient un exemple où les deux formats diffèrent. Aucun numéro de démonstration du fichier n'est réutilisé.

Le serveur conserve une référence stable avant l'appel susceptible de débiter le portefeuille. Un timeout n'autorise jamais un second débit : la transaction est recherchée par référence. Un devis échoué avant transmission peut être retenté. Les numéros nationaux commençant par 6 sont normalisés avec 237 ; les entrées mal typées sont refusées.

Le webhook `/api/v1/s3p/webhook` vérifie `X-Signature` sur les octets exacts du corps, valide `X-Ptn`, `X-Delivery` et le contenu, puis écrit dans `s3p_callback_inbox` avant de répondre. Il n'appelle pas le prestataire dans la requête HTTP. Le planificateur interroge ensuite `/verifytx`. Les doublons sont identifiés par l'empreinte du corps signé, pas uniquement par un en-tête non signé. Un callback différé par la cadence de dix secondes ou une indisponibilité reste enregistré et est repris avec un délai croissant.

Aucun statut transmis par le navigateur ne confirme un paiement. Les contrôles comparent référence, PTN, marchand, service, montant exact et devise. Le produit de paiement et le portefeuille retournés sont comparés lorsqu'ils sont présents ; leur absence ne remplace jamais les autres contrôles. Un contexte figé associe le devis au compte S3P, à l'environnement, au service et au format du portefeuille. Une ancienne transaction sans ce contexte exige un rapprochement manuel documenté ; aucune association n'est devinée. Les PTN, numéros de reçu, codes de retour et empreintes des réponses sont conservés. Les codes de vérification fournisseur et le contexte interne ne sont pas exposés à l'API client.

Un statut `REVERSED` exclut la souscription du portefeuille et ne peut être remplacé automatiquement par un succès ultérieur. Les contradictions entre états terminaux sont consignées pour examen. Un callback d'annulation encore non rapproché bloque la valorisation différée.

La date du callback signé `SUCCESS` est utilisée après confirmation indépendante du succès par `/verifytx`. Le format historique `YYYY-MM-DD HH:MM:SS` n'indique pas son fuseau : `S3P_TIMESTAMP_TIMEZONE` doit être renseigné seulement après confirmation de Maviance. Le format ISO 8601 avec fuseau est aussi accepté. Les dates impossibles, futures ou antérieures à la demande sont refusées.

Le SDK qualifie `verifytx.timestamp` d'heure de traitement, ce qui ne suffit pas à prouver la réception. `S3P_VERIFY_TIMESTAMP_IS_RECEIPT=false` est donc le défaut. Un succès obtenu par consultation seule reste `awaiting_payment_date`, sans parts attribuées, jusqu'au callback daté. Activer ce paramètre uniquement après confirmation contractuelle que ce champ représente la réception pour les services configurés. La date de `clearingDate` n'est pas utilisée pour les parts. L'absence permanente de callback nécessite une intervention de rapprochement ; il n'existe pas de bouton permettant d'inventer cette date.

Références fournisseur :
- https://apidocs.smobilpay.com/s3papi/S3P-API-Concepts.1578338258.html
- https://apidocs.smobilpay.com/s3papi/Callback-support-via-Webhook.1578338315.html
- https://apidocs.smobilpay.com/s3papi/Error-Codes-%26-Server-Responses.1578338301.html

`MOBILE_MONEY_SIMULATION=true` est réservé aux tests automatisés Laravel utilisant SQLite `:memory:`. Sur une base persistante ou hors tests, ce paramètre rend les opérateurs indisponibles. Le désactiver avant toute recette S3P. Les identifiants `SIM-*` ne peuvent être confirmés par la passerelle réelle. Une ancienne souscription simulée locale a été placée en `simulation_review`, statut `À vérifier`, sans suppression de son historique. Un éventuel reçu de simulation antérieur ne constitue pas une preuve d'encaissement.

## Documents et autorisations

Les fichiers résident sur le disque privé `payment_private`, hors du répertoire public. Le serveur contrôle le type, la taille et les signatures de fichiers, utilise un nom aléatoire, et conserve un SHA-256. Télécharger exige l'identité du propriétaire ou `view_payment_proof`, un scan sain et une empreinte intacte. Les réponses imposent téléchargement et absence de cache. Le service worker exclut les API et réponses privées.

Installer ClamAV et ses signatures actualisées, puis configurer `PAYMENT_PROOF_SCANNER` avec le chemin absolu de `clamscan`. L'absence, l'échec ou le timeout du scanner laisse les fichiers en quarantaine. Ne jamais contourner cette protection en marquant manuellement les fichiers sains. Aucun antivirus n'est configuré dans l'environnement local vérifié à cette date.

Attribuer explicitement les permissions `view_payment_proof`, `review_payment_proof`, `confirm_bank_payment` et `review_subscription_compliance` aux personnes compétentes. Les permissions existent mais ne sont pas distribuées automatiquement à tous les administrateurs. Le super-administrateur conserve son accès global existant. La revue conformité ne vaut pas confirmation d'encaissement. La confirmation bancaire reste une action comptable unique ; un dispositif de double approbation n'est pas implémenté ici.

## Exploitation

Les migrations de paiement et de bénéficiaire sont déjà marquées exécutées dans la base locale inspectée. Pour un autre environnement, appliquer les migrations après sauvegarde, puis reconstruire le cache de configuration et redémarrer les workers.

Le planificateur Laravel doit être réellement exécuté chaque minute par le système d'exploitation ; le simple enregistrement dans `app/Console/Kernel.php` ne suffit pas. Les tâches prévues sont `payments:reconcile` chaque minute et `payments:scan-proofs` toutes les cinq minutes. Le worker de queue doit aussi traiter les reçus et notifications. Leur fonctionnement permanent sur un hébergement n'a pas été vérifié ici.

Les consultations automatiques concernent les transactions en attente ou incertaines. Les succès ne sont plus interrogés périodiquement : les annulations passent par les callbacks durables. Un rapprochement comptable des relevés reste nécessaire pour détecter une notification définitivement absente. Surveiller les callbacks non traités, `mobile_reconciliation_failed`, `mobile_terminal_conflict`, les quarantaines, les dates de réception et VL manquantes. Les événements sont conservés en base ; ils ne constituent pas un journal externe inviolable.

La migration `2026_09_17_000001_harden_s3p_reconciliation` ajoute le contexte figé, les détails de transaction et la boîte de réception des callbacks. Elle a été appliquée localement après une sauvegarde SQLite cohérente dans le stockage privé. Exécuter `php artisan payments:s3p-check` pour un contrôle de configuration sans secrets. Avec `--network`, la commande authentifie puis lit uniquement `/ping` et `/cashout` : aucun devis ni débit, même lorsque l'activation des encaissements est désactivée. La simulation doit être désactivée pour ce contrôle réseau.

Avant production : HTTPS, secrets hors dépôt, comptes administrateurs protégés, sauvegardes et restauration testées, permissions minimales, antivirus fonctionnel, workers/planificateur supervisés, puis recette fournisseur des deux opérateurs (succès, refus, timeout, webhook doublé, montant incohérent, annulation et retard de confirmation). Aucun système ne peut être garanti « impiratable ».

## Vérification locale

La suite ciblée couvre les souscriptions, webhooks, e-nkap, valorisation du portefeuille et nouveaux workflows sécurisés. Elle utilise une base de test isolée et des réponses S3P contrôlées. Une recette distincte a effectivement appelé collectstd et verifytx sur le staging, avec succès pour Orange et MTN. Le build Vue est vérifié séparément. Les migrations et routes sont inspectées. Les appels HTTP locaux vérifient la disponibilité des moyens de paiement et le refus de téléchargement anonyme. La recette visuelle, le callback depuis Maviance et les encaissements en production restent à effectuer.
