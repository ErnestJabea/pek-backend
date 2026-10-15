<x-filament-panels::page>
    @php
        $statement = $this->statementData;
        $client = $statement['client'];
        $period = $statement['period'];
        $consolide = $statement['consolide'];
        $fundStatements = $statement['fund_statements'];
    @endphp

    {{-- 1. Fiche Client & Ancienneté --}}
    <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold text-xl flex items-center justify-center shrink-0 border border-amber-500/20">
                    {{ strtoupper(substr($client['nom_complet'] ?: 'C', 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $client['nom_complet'] }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            Client Actif
                        </span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-y-1 gap-x-4 text-sm text-gray-500 dark:text-gray-400">
                        @if($client['email'])
                            <span class="flex items-center gap-1">
                                <x-heroicon-m-envelope class="w-4 h-4 text-gray-400" />
                                {{ $client['email'] }}
                            </span>
                        @endif
                        @if($client['telephone'])
                            <span class="flex items-center gap-1">
                                <x-heroicon-m-phone class="w-4 h-4 text-gray-400" />
                                {{ $client['telephone'] }}
                            </span>
                        @endif
                        @if($client['adresse'])
                            <span class="flex items-center gap-1">
                                <x-heroicon-m-map-pin class="w-4 h-4 text-gray-400" />
                                {{ $client['adresse'] }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Bloc Ancienneté & 1ère Souscription --}}
            <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-lg p-3.5 text-right md:min-w-[240px]">
                <div class="text-xs uppercase tracking-wider font-semibold text-amber-700 dark:text-amber-400">
                    Historique & Ancienneté
                </div>
                <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                    1ère souscription : <span class="font-bold text-amber-900 dark:text-amber-300">{{ $client['first_subscription_date'] ?? 'Aucune' }}</span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Ancienneté : {{ $client['seniority'] }}
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Barre de Filtrage & Période d'Analyse --}}
    <div class="p-5 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-gray-700">
            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                <x-heroicon-m-calendar-days class="w-5 h-5 text-amber-500" />
                Période d'analyse : <span class="text-amber-600 dark:text-amber-400 font-bold">{{ $period['label'] }}</span>
            </div>
            {{-- Raccourcis temporels --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" wire:click="setPeriodCurrentMonth" class="px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                    Ce mois
                </button>
                <button type="button" wire:click="setPeriodLastMonth" class="px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                    Mois précédent
                </button>
                <button type="button" wire:click="setPeriodYearToDate" class="px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                    Année en cours (YTD)
                </button>
                <button type="button" wire:click="setPeriodAllHistory" class="px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                    Tout l'historique
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date de début</label>
                <input type="date" wire:model.live="startDate" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date de fin (Date de Valorisation)</label>
                <input type="date" wire:model.live="endDate" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Fonds / FCP concerné</label>
                <select wire:model.live="productId" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <option value="">Tous les fonds (Consolidé)</option>
                    @foreach($this->products as $p)
                        <option value="{{ $p->id }}">{{ $p->libelle }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- 3. Cartes KPIs Consolidées --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Valorisation Totale --}}
        <div class="p-5 bg-gradient-to-br from-amber-50 to-orange-50 dark:from-gray-800 dark:to-gray-800 rounded-xl border border-amber-200/80 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider font-bold text-amber-700 dark:text-amber-400">Actif Global à Date</span>
                <x-heroicon-o-banknotes class="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                {{ $consolide['total_valorisation_display'] }} <span class="text-xs font-bold text-amber-600 dark:text-amber-400">XAF</span>
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Valorisation au {{ $period['end_date_display'] }}
            </div>
        </div>

        {{-- Capital Total Investi --}}
        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">Capital Net Investi</span>
                <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-gray-400" />
            </div>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                {{ $consolide['total_investi_display'] }} <span class="text-xs font-bold text-gray-500">XAF</span>
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Souscriptions nettes cumulées
            </div>
        </div>

        {{-- Plus-Value / Moins-Value --}}
        @php
            $isPositive = $consolide['total_plus_value'] >= 0;
            $yieldPct = $consolide['total_investi'] > 0 
                ? round(($consolide['total_plus_value'] / $consolide['total_investi']) * 100, 2) 
                : 0.00;
        @endphp
        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">Plus / Moins-Value</span>
                <span class="inline-flex items-center text-xs font-bold {{ $isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    {{ $isPositive ? '+' : '' }}{{ number_format($yieldPct, 2, ',', ' ') }} %
                </span>
            </div>
            <div class="mt-2 text-2xl font-black {{ $isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                {{ $consolide['total_plus_value_display'] }} <span class="text-xs font-bold">XAF</span>
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Performance latente globale
            </div>
        </div>

        {{-- Fonds Détenus --}}
        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">Fonds Concernés</span>
                <x-heroicon-o-chart-pie class="w-5 h-5 text-gray-400" />
            </div>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">
                {{ $consolide['nb_fonds'] }} <span class="text-xs font-medium text-gray-500">fond(s)</span>
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $this->productId ? 'Filtre spécifique actif' : 'Vue portefeuille globale' }}
            </div>
        </div>
    </div>

    {{-- 4. Détail par Fonds (Exactement conforme au relevé officiel) --}}
    @forelse($fundStatements as $fs)
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-6">
            {{-- En-tête du Fonds --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b-2 border-amber-500">
                <div>
                    <h3 class="text-lg font-black uppercase tracking-wide text-amber-700 dark:text-amber-400">
                        {{ $fs['fcp_nom'] }}
                    </h3>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Code ISIN / Référence : {{ $fs['product']->code ?? 'FCP' }} &bull; VL du {{ $fs['synthetique']['date_vl_effective'] }} : <strong class="text-gray-900 dark:text-white">{{ $fs['synthetique']['vl_a_date_display'] }} XAF</strong>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('client.statement.pdf', ['client' => $this->record->id, 'start_date' => $this->startDate, 'end_date' => $this->endDate, 'product_id' => $fs['product']->id]) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-gray-900 transition">
                        <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                        PDF de ce fond
                    </a>
                </div>
            </div>

            {{-- SECTION I : DÉTAIL SYNTHÉTIQUE --}}
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                    I. DÉTAIL SYNTHÉTIQUE
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-[#E5A812] text-black font-extrabold text-center border-b border-black">
                                <th class="p-2.5 border-r border-black">Date de Valorisation</th>
                                <th class="p-2.5 border-r border-black">VL à date (XAF)</th>
                                <th class="p-2.5 border-r border-black">Capital Total Investi</th>
                                <th class="p-2.5 border-r border-black">Nombre de Parts Cumulé</th>
                                <th class="p-2.5 border-r border-black">Valorisation Portefeuille</th>
                                <th class="p-2.5">Plus Value / Moins Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 text-center font-medium">
                            <tr>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700">{{ $fs['synthetique']['date_valorisation'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold">{{ $fs['synthetique']['vl_a_date_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold">{{ $fs['synthetique']['capital_total_investi_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold">{{ $fs['synthetique']['nb_parts_cumule_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-black text-gray-900 dark:text-white">{{ $fs['synthetique']['valorisation_portefeuille_display'] }}</td>
                                <td class="p-2.5 text-right font-extrabold {{ $fs['synthetique']['plus_value'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ $fs['synthetique']['plus_value_display'] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- SECTION II : DÉTAILS DES TRANSACTIONS (PARTS) --}}
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                    II. DÉTAILS DES TRANSACTIONS (PARTS)
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-[#E5A812] text-black font-extrabold text-center border-b border-black">
                                <th class="p-2.5 border-r border-black">Désignation</th>
                                <th class="p-2.5 border-r border-black">Nb Parts (Mois précédent)</th>
                                <th class="p-2.5 border-r border-black">Montant brut investi</th>
                                <th class="p-2.5 border-r border-black">Frais de souscription</th>
                                <th class="p-2.5 border-r border-black">Nb Parts (Mois)</th>
                                <th class="p-2.5 border-r border-black">Nb Parts (Rachetées)</th>
                                <th class="p-2.5">Nombre de Parts Actuel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 text-center font-medium">
                            <tr>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-left">{{ $fs['mouvements']['designation'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right">{{ $fs['mouvements']['nb_parts_precedent_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right">{{ $fs['mouvements']['montant_brut_investi_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right">{{ $fs['mouvements']['frais_souscription_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ $fs['mouvements']['nb_parts_mois_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold text-rose-600 dark:text-rose-400">{{ $fs['mouvements']['nb_parts_rachetees_display'] }}</td>
                                <td class="p-2.5 text-right font-black text-gray-900 dark:text-white">{{ $fs['mouvements']['nb_parts_actuel_display'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Liste détaillée des souscriptions intervenues sur la période (si applicable) --}}
                @if(!empty($fs['mouvements']['details_transactions']))
                    <div class="mt-3 p-3.5 bg-gray-50 dark:bg-gray-900/60 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-1.5">
                            <x-heroicon-m-queue-list class="w-4 h-4 text-amber-500" />
                            Souscriptions de la période ({{ count($fs['mouvements']['details_transactions']) }} opération(s))
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead>
                                    <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                        <th class="py-1 px-2">Date Valeur</th>
                                        <th class="py-1 px-2">Référence</th>
                                        <th class="py-1 px-2 text-right">Montant Brut</th>
                                        <th class="py-1 px-2 text-right">Frais</th>
                                        <th class="py-1 px-2 text-right">Montant Net</th>
                                        <th class="py-1 px-2 text-right">VL Appliquée</th>
                                        <th class="py-1 px-2 text-right font-bold">Parts Allouées</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($fs['mouvements']['details_transactions'] as $tx)
                                        <tr>
                                            <td class="py-1.5 px-2">{{ $tx['date'] }}</td>
                                            <td class="py-1.5 px-2 font-mono text-gray-600 dark:text-gray-400">{{ $tx['reference'] }}</td>
                                            <td class="py-1.5 px-2 text-right">{{ number_format($tx['montant_total'], 0, ',', ' ') }} XAF</td>
                                            <td class="py-1.5 px-2 text-right text-gray-500">{{ number_format($tx['frais'], 0, ',', ' ') }} XAF</td>
                                            <td class="py-1.5 px-2 text-right font-medium">{{ number_format($tx['montant_net'], 0, ',', ' ') }} XAF</td>
                                            <td class="py-1.5 px-2 text-right">{{ number_format($tx['vl'], 2, ',', ' ') }}</td>
                                            <td class="py-1.5 px-2 text-right font-bold text-emerald-600 dark:text-emerald-400">+{{ number_format($tx['nb_parts'], 2, ',', ' ') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- SECTION III : RÉGULARISATION --}}
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                    III. REGULARISATION
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-[#E5A812] text-black font-extrabold text-center border-b border-black">
                                <th class="p-2.5 border-r border-black">Total encaissé</th>
                                <th class="p-2.5 border-r border-black">Total des frais de souscription</th>
                                <th class="p-2.5 border-r border-black">Montant total des souscriptions</th>
                                <th class="p-2.5">Montant regularisé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900 text-center font-medium">
                            <tr>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold">{{ $fs['regularisation']['total_encaisse_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right">{{ $fs['regularisation']['total_frais_display'] }}</td>
                                <td class="p-2.5 border-r border-gray-200 dark:border-gray-700 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ $fs['regularisation']['montant_total_souscriptions_display'] }}</td>
                                <td class="p-2.5 text-right">{{ $fs['regularisation']['montant_regularise_display'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Encadré Valorisation Totale (Bas) --}}
            <div class="p-4 rounded-xl border-2 border-[#8C4B18] bg-amber-50/50 dark:bg-amber-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <span class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">
                    VALORISATION AU {{ $fs['valorisation_finale']['date_label'] }}
                </span>
                <span class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                    {{ $fs['valorisation_finale']['montant_display'] }} <span class="text-xs font-bold text-amber-700 dark:text-amber-400">XAF</span>
                </span>
            </div>
        </div>
    @empty
        <div class="p-12 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-gray-400 mb-3" />
            <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">Aucun mouvement ni détention pour ce client</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Aucune souscription validée n'a été enregistrée pour ce client sur la période sélectionnée.
            </p>
        </div>
    @endforelse

</x-filament-panels::page>
