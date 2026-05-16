<x-mail::message>
# Merci pour votre investissement !

Bonjour {{ $subscription->user->first_name }},

Votre demande de souscription au fonds **{{ $subscription->product->libelle }}** a bien été enregistrée.

**Détails de la transaction :**
- **Référence :** {{ $subscription->reference_transaction }}
- **Parts :** {{ $subscription->nb_parts }}
- **Montant Total :** {{ number_format($subscription->montant_total, 0, ',', ' ') }} FCFA
- **Mode de paiement :** {{ strtoupper(str_replace('_', ' ', $subscription->moyen_paiement)) }}
- **Statut :** En attente de validation

Vous trouverez en pièce jointe votre reçu provisoire au format PDF.

<x-mail::button :url="config('app.url') . '/dashboard'">
Accéder à mon Dashboard
</x-mail::button>

Si vous n'avez pas initié cette transaction, veuillez contacter notre support immédiatement.

Cordialement,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
