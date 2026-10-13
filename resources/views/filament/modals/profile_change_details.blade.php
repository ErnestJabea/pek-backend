<div class="space-y-4 text-sm text-slate-700">
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs text-slate-400 uppercase font-black">Client</p>
            <p class="font-bold text-slate-800">{{ $record->user?->first_name }} {{ $record->user?->last_name }}</p>
            <p class="text-xs text-slate-500">{{ $record->user?->email }} | {{ $record->user?->phone }}</p>
        </div>
        <div>
            <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase {{ $record->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($record->status === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                {{ $record->status === 'approved' ? 'Approuvée' : ($record->status === 'rejected' ? 'Rejetée' : 'En attente') }}
            </span>
        </div>
    </div>

    @if($record->request_type === 'category_nature')
        <div class="grid grid-cols-2 gap-4">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <p class="text-xs text-slate-400 font-bold uppercase mb-2">Valeurs Actuelles</p>
                <p class="text-xs text-slate-500 font-semibold">Nature :</p>
                <p class="font-bold text-slate-800 mb-2">{{ $record->current_data['nature_client'] ?? 'Non définie' }}</p>
                <p class="text-xs text-slate-500 font-semibold">Catégorie :</p>
                <p class="font-bold text-slate-800">{{ $record->current_data['categorie_client'] ?? 'Non définie' }}</p>
            </div>
            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <p class="text-xs text-emerald-700 font-bold uppercase mb-2">Nouvelles Valeurs Demandées</p>
                <p class="text-xs text-emerald-600 font-semibold">Nature demandée :</p>
                <p class="font-black text-emerald-900 mb-2">{{ $record->requested_data['nature_client'] ?? 'N/A' }}</p>
                <p class="text-xs text-emerald-600 font-semibold">Catégorie demandée :</p>
                <p class="font-black text-emerald-900">{{ $record->requested_data['categorie_client'] ?? 'N/A' }}</p>
            </div>
        </div>
    @else
        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-2">
            <p class="text-xs text-slate-400 font-bold uppercase">Coordonnées Bancaires Soumises</p>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <span class="text-slate-400">Établissement :</span>
                    <p class="font-bold text-slate-800">{{ $record->requested_data['bank_name'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-400">Titulaire :</span>
                    <p class="font-bold text-slate-800">{{ $record->requested_data['account_holder_name'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-400">N° de Compte :</span>
                    <p class="font-bold text-slate-800">{{ $record->requested_data['account_number'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-slate-400">IBAN / SWIFT :</span>
                    <p class="font-bold text-slate-800">{{ $record->requested_data['iban'] ?? ($record->requested_data['swift_bic'] ?? '-') }}</p>
                </div>
                @if(!empty($record->requested_data['bank_code']))
                <div class="col-span-2 bg-white p-2 rounded-lg border border-slate-200">
                    <span class="text-slate-400">Décomposition RIB :</span>
                    <p class="font-mono font-bold text-slate-800 text-[11px]">
                        Code Banque: {{ $record->requested_data['bank_code'] }} | Guichet: {{ $record->requested_data['branch_code'] }} | Clé: {{ $record->requested_data['rib_key'] }}
                    </p>
                </div>
                @endif
            </div>

            @if($record->document_path)
                <div class="pt-3 border-t border-slate-200">
                    <p class="text-xs text-slate-400 font-bold uppercase mb-1">Justificatif de RIB joint :</p>
                    <a href="{{ asset('storage/' . $record->document_path) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-slate-800 transition-colors">
                        Ouvrir le justificatif officiel (PDF / Image) ↗
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if($record->rejection_reason)
        <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl text-xs text-rose-800">
            <span class="font-bold">Motif de rejet enregistré :</span> {{ $record->rejection_reason }}
        </div>
    @endif
</div>
