<x-filament-panels::page>
    @php
        $statement = $this->statementData;
        $client = $statement['client'];
        $period = $statement['period'];
        $consolide = $statement['consolide'];
        $fundStatements = $statement['fund_statements'];
    @endphp

    {{-- Styles CSS intégrés pour garantir 100% du rendu de la charte KORI (indépendant de Tailwind purge) --}}
    <style>
        .kori-scope {
            --kori-green: #009A4D;
            --kori-green-hover: #007A3D;
            --kori-green-light: #E8F8F0;
            --kori-gold: #E5A812;
            --kori-gold-dark: #C67C00;
            --kori-gold-light: #FFF8E7;
            --kori-brown: #482010;
            --kori-brown-border: #8C4B18;
            --kori-brown-light: #FAF4EB;
        }

        /* Bento Grid 4 colonnes robuste */
        .kori-kpi-grid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 16px !important;
            margin-top: 14px !important;
            margin-bottom: 20px !important;
        }
        @media (max-width: 1100px) {
            .kori-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 640px) {
            .kori-kpi-grid {
                grid-template-columns: 1fr !important;
            }
        }

        /* Cartes KPI aux couleurs de la charte */
        .kori-kpi-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kori-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.07);
        }
        .dark .kori-kpi-card {
            background: #1E293B;
            border-color: #334155;
        }

        .kori-kpi-actif {
            border-top: 4px solid var(--kori-green);
            background: linear-gradient(180deg, #F0FDF4 0%, #FFFFFF 60%);
        }
        .dark .kori-kpi-actif {
            background: linear-gradient(180deg, rgba(0, 154, 77, 0.15) 0%, #1E293B 70%);
        }

        .kori-kpi-investi {
            border-top: 4px solid var(--kori-gold);
            background: linear-gradient(180deg, #FFFDF5 0%, #FFFFFF 60%);
        }
        .dark .kori-kpi-investi {
            background: linear-gradient(180deg, rgba(229, 168, 18, 0.15) 0%, #1E293B 70%);
        }

        .kori-kpi-perf {
            border-top: 4px solid #10B981;
            background: linear-gradient(180deg, #F0FDF4 0%, #FFFFFF 60%);
        }
        .dark .kori-kpi-perf {
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.15) 0%, #1E293B 70%);
        }
        .kori-kpi-perf.negative {
            border-top: 4px solid #F43F5E;
            background: linear-gradient(180deg, #FFF1F2 0%, #FFFFFF 60%);
        }
        .dark .kori-kpi-perf.negative {
            background: linear-gradient(180deg, rgba(244, 63, 94, 0.15) 0%, #1E293B 70%);
        }

        .kori-kpi-fonds {
            border-top: 4px solid var(--kori-brown-border);
            background: linear-gradient(180deg, #FDF9F5 0%, #FFFFFF 60%);
        }
        .dark .kori-kpi-fonds {
            background: linear-gradient(180deg, rgba(140, 75, 24, 0.15) 0%, #1E293B 70%);
        }

        /* Pastilles d'icônes rondes */
        .kori-icon-bubble {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Boutons de périodes rapides */
        .kori-pill-btn {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .kori-pill-btn.active {
            background-color: var(--kori-green) !important;
            color: #FFFFFF !important;
            box-shadow: 0 2px 8px rgba(0, 154, 77, 0.3) !important;
        }
        .kori-pill-btn.inactive {
            background-color: #F1F5F9;
            color: #475569;
            border-color: #E2E8F0;
        }
        .kori-pill-btn.inactive:hover {
            background-color: #E2E8F0;
            color: #1E293B;
        }
        .dark .kori-pill-btn.inactive {
            background-color: #334155;
            color: #CBD5E1;
            border-color: #475569;
        }
        .dark .kori-pill-btn.inactive:hover {
            background-color: #475569;
            color: #FFFFFF;
        }

        /* Tables financières officielles conformes à la charte */
        .kori-statement-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 8px !important;
            margin-bottom: 16px !important;
            font-size: 12px !important;
        }
        .kori-statement-table th {
            background-color: #E5A812 !important;
            color: #000000 !important;
            font-weight: 800 !important;
            text-align: center !important;
            vertical-align: middle !important;
            padding: 9px 8px !important;
            border: 1px solid #000000 !important;
            font-size: 11px !important;
            letter-spacing: 0.2px !important;
            text-transform: uppercase !important;
        }
        .kori-statement-table td {
            background-color: #FFFFFF !important;
            color: #1A1A1A !important;
            font-weight: 600 !important;
            padding: 10px 10px !important;
            border: 1px solid #000000 !important;
            vertical-align: middle !important;
            font-size: 12.5px !important;
        }
        .dark .kori-statement-table td {
            background-color: #0F172A !important;
            color: #F8FAFC !important;
            border-color: #475569 !important;
        }
        .kori-statement-table td.align-right {
            text-align: right !important;
        }
        .kori-statement-table td.align-left {
            text-align: left !important;
        }
        .kori-statement-table td.align-center {
            text-align: center !important;
        }

        /* Encadré Valorisation Totale officiel */
        .kori-valorisation-box {
            border: 3px solid var(--kori-brown-border) !important;
            background: linear-gradient(135deg, #FFFDF8 0%, #FAF2E6 100%) !important;
            border-radius: 12px !important;
            padding: 18px 24px !important;
            box-shadow: 0 4px 15px rgba(140, 75, 24, 0.09) !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-top: 20px !important;
        }
        .dark .kori-valorisation-box {
            background: linear-gradient(135deg, #2D1A10 0%, #1F120A 100%) !important;
            border-color: #A35A22 !important;
        }
    </style>

    <div class="kori-scope space-y-5">

        {{-- 1. Fiche Signalétique & Ancienneté du Client (Bento Card avec bordure décorative) --}}
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{-- Ligne supérieure aux couleurs de la charte KORI --}}
            <div class="h-1.5 w-full bg-gradient-to-r from-[#009A4D] via-[#E5A812] to-[#8C4B18]"></div>

            <div class="p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                {{-- Identité & Coordonnées --}}
                <div class="flex items-start gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#009A4D] to-[#006834] text-white font-black text-2xl flex items-center justify-center shrink-0 shadow-md shadow-emerald-500/20 border border-white/20">
                        {{ strtoupper(substr($client['nom_complet'] ?: 'C', 0, 2)) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white tracking-tight">
                                {{ $client['nom_complet'] }}
                            </h2>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Client FCP Actif
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-y-1.5 gap-x-5 text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                            @if($client['email'])
                                <span class="flex items-center gap-1.5 font-medium">
                                    <x-heroicon-m-envelope class="w-4 h-4 text-[#009A4D]" />
                                    {{ $client['email'] }}
                                </span>
                            @endif
                            @if($client['telephone'])
                                <span class="flex items-center gap-1.5 font-medium">
                                    <x-heroicon-m-phone class="w-4 h-4 text-[#E5A812]" />
                                    {{ $client['telephone'] }}
                                </span>
                            @endif
                            @if($client['adresse'])
                                <span class="flex items-center gap-1.5 font-medium">
                                    <x-heroicon-m-map-pin class="w-4 h-4 text-[#8C4B18]" />
                                    {{ $client['adresse'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Bloc Ancienneté & 1ère Souscription --}}
                <div class="lg:min-w-[280px] bg-gradient-to-br from-[#FFFDF8] to-[#FFF8EA] dark:from-gray-900 dark:to-gray-800/80 border border-[#F5E5C9] dark:border-amber-900/40 rounded-xl p-4 text-right shadow-sm">
                    <div class="flex items-center justify-end gap-1.5 text-xs uppercase tracking-wider font-extrabold text-[#C67C00] dark:text-amber-400">
                        <x-heroicon-m-calendar class="w-4 h-4 text-[#E5A812]" />
                        Historique & Ancienneté
                    </div>
                    <div class="mt-1.5 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        1ère souscription : 
                        @if($client['first_subscription_date'])
                            <span class="font-extrabold text-[#009A4D] dark:text-emerald-400">{{ $client['first_subscription_date'] }}</span>
                        @else
                            <span class="font-bold text-gray-400 italic">Aucune</span>
                        @endif
                    </div>
                    <div class="mt-1 inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded-md bg-[#FFF0D4] text-[#8C4B18] dark:bg-amber-950 dark:text-amber-300">
                        {{ $client['seniority'] }}
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Barre de Filtrage & Période d'Analyse (Ergonomique et intuitive) --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-amber-500/10 text-[#C67C00] flex items-center justify-center font-bold">
                        <x-heroicon-m-adjustments-horizontal class="w-5 h-5 text-[#E5A812]" />
                    </div>
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Période du relevé</span>
                        <div class="text-sm font-black text-gray-900 dark:text-white">
                            {{ $period['label'] }}
                        </div>
                    </div>
                </div>

                {{-- Boutons d'accès rapide avec surbrillance de l'état actif --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="setPeriodCurrentMonth"
                            class="kori-pill-btn {{ $activeFilter === 'current_month' ? 'active' : 'inactive' }}">
                        Ce mois
                    </button>
                    <button type="button" wire:click="setPeriodLastMonth"
                            class="kori-pill-btn {{ $activeFilter === 'last_month' ? 'active' : 'inactive' }}">
                        Mois précédent
                    </button>
                    <button type="button" wire:click="setPeriodYearToDate"
                            class="kori-pill-btn {{ $activeFilter === 'ytd' ? 'active' : 'inactive' }}">
                        Année en cours (YTD)
                    </button>
                    <button type="button" wire:click="setPeriodAllHistory"
                            class="kori-pill-btn {{ $activeFilter === 'all' ? 'active' : 'inactive' }}">
                        Tout l'historique
                    </button>
                    @if($activeFilter === 'custom')
                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            Période personnalisée
                        </span>
                    @endif
                </div>
            </div>

            {{-- Champs de sélection de dates et de fonds --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Date de début
                    </label>
                    <div class="relative">
                        <input type="date" wire:model.live="startDate"
                               class="w-full text-sm font-semibold rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#009A4D] focus:ring-[#009A4D]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Date de fin (Date de Valorisation)
                    </label>
                    <div class="relative">
                        <input type="date" wire:model.live="endDate"
                               class="w-full text-sm font-semibold rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#009A4D] focus:ring-[#009A4D]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        Fonds / FCP concerné
                    </label>
                    <select wire:model.live="productId"
                            class="w-full text-sm font-semibold rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#009A4D] focus:ring-[#009A4D]">
                        <option value="">Tous les fonds (Consolidé multi-pages)</option>
                        @foreach($this->products as $p)
                            <option value="{{ $p->id }}">{{ $p->libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- 3. Cartes KPIs Consolidées (Bento Grid 4 colonnes robuste avec accents de couleur) --}}
        <div class="kori-kpi-grid">
            {{-- KPI 1 : Actif Global à Date --}}
            <div class="kori-kpi-card kori-kpi-actif">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-extrabold text-[#009A4D]">
                        Actif Global à Date
                    </span>
                    <div class="kori-icon-bubble bg-emerald-100 text-[#009A4D] dark:bg-emerald-950/60 dark:text-emerald-400">
                        <x-heroicon-m-banknotes class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                    {{ $consolide['total_valorisation_display'] }} <span class="text-xs font-extrabold text-[#009A4D]">XAF</span>
                </div>
                <div class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                    Valorisation au {{ $period['end_date_display'] }}
                </div>
            </div>

            {{-- KPI 2 : Capital Total Investi --}}
            <div class="kori-kpi-card kori-kpi-investi">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-extrabold text-[#C67C00]">
                        Capital Net Investi
                    </span>
                    <div class="kori-icon-bubble bg-amber-100 text-[#C67C00] dark:bg-amber-950/60 dark:text-amber-400">
                        <x-heroicon-m-arrow-trending-up class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                    {{ $consolide['total_investi_display'] }} <span class="text-xs font-extrabold text-gray-400">XAF</span>
                </div>
                <div class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                    Souscriptions nettes cumulées
                </div>
            </div>

            {{-- KPI 3 : Plus / Moins-Value --}}
            @php
                $isPositive = $consolide['total_plus_value'] >= 0;
                $yieldPct = $consolide['total_investi'] > 0 
                    ? round(($consolide['total_plus_value'] / $consolide['total_investi']) * 100, 2) 
                    : 0.00;
            @endphp
            <div class="kori-kpi-card kori-kpi-perf {{ $isPositive ? '' : 'negative' }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-extrabold {{ $isPositive ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                        Plus / Moins-Value
                    </span>
                    <span class="inline-flex items-center text-xs font-black px-2 py-0.5 rounded-full {{ $isPositive ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                        {{ $isPositive ? '+' : '' }}{{ number_format($yieldPct, 2, ',', ' ') }} %
                    </span>
                </div>
                <div class="mt-2.5 text-2xl sm:text-3xl font-black tracking-tight {{ $isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    {{ $consolide['total_plus_value_display'] }} <span class="text-xs font-extrabold">XAF</span>
                </div>
                <div class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                    Gain latent net de frais
                </div>
            </div>

            {{-- KPI 4 : Fonds Concernés --}}
            <div class="kori-kpi-card kori-kpi-fonds">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-extrabold text-[#8C4B18] dark:text-amber-300">
                        Fonds Concernés
                    </span>
                    <div class="kori-icon-bubble bg-orange-100 text-[#8C4B18] dark:bg-orange-950/60 dark:text-orange-400">
                        <x-heroicon-m-chart-pie class="w-5 h-5" />
                    </div>
                </div>
                <div class="mt-2.5 text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">
                    {{ $consolide['nb_fonds'] }} <span class="text-xs font-bold text-gray-500">fond(s)</span>
                </div>
                <div class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                    {{ $this->productId ? 'Filtre spécifique sélectionné' : 'Portefeuille multi-fonds' }}
                </div>
            </div>
        </div>

        {{-- 4. Relevés par Fonds (Tableaux officiels respectant 100% la charte graphique KORI) --}}
        @forelse($fundStatements as $fs)
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">

                {{-- En-tête du Fonds avec titre souligné en or et bouton d'action --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b-2 border-[#E5A812]">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-[#C67C00] font-black flex items-center justify-center text-sm border border-amber-500/30">
                            FCP
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-black uppercase tracking-wide text-[#8C4B18] dark:text-amber-400">
                                {{ $fs['fcp_nom'] }}
                            </h3>
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                Code ISIN : <strong class="text-gray-800 dark:text-gray-200">{{ $fs['product']->code ?? 'FCP' }}</strong> &bull; 
                                VL au {{ $fs['synthetique']['date_vl_effective'] }} : <strong class="text-[#009A4D] font-extrabold">{{ $fs['synthetique']['vl_a_date_display'] }} XAF</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Bouton téléchargement PDF dédié au fonds --}}
                    <div>
                        <a href="{{ route('client.statement.pdf', ['client' => $this->record->id, 'start_date' => $this->startDate, 'end_date' => $this->endDate, 'product_id' => $fs['product']->id]) }}"
                           target="_blank"
                           class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-black rounded-xl shadow-sm transition"
                           style="background-color: #E5A812 !important; color: #000000 !important; border: 1px solid #C67C00 !important; font-weight: 800 !important;">
                            <x-heroicon-m-arrow-down-tray class="w-4 h-4 text-black" />
                            Télécharger PDF de ce fonds
                        </a>
                    </div>
                </div>

                {{-- SECTION I : DÉTAIL SYNTHÉTIQUE --}}
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#E5A812]"></span>
                        I. DÉTAIL SYNTHÉTIQUE
                    </div>
                    <div class="overflow-x-auto">
                        <table class="kori-statement-table">
                            <thead>
                                <tr>
                                    <th style="width: 17%;">Date de Valorisation</th>
                                    <th style="width: 17%;">VL à date (XAF)</th>
                                    <th style="width: 17%;">Capital Total Investi</th>
                                    <th style="width: 16%;">Nombre de Parts Cumulé</th>
                                    <th style="width: 17%;">Valorisation Portefeuille</th>
                                    <th style="width: 16%;">Plus Value / Moins Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="align-center font-bold">{{ $fs['synthetique']['date_valorisation'] }}</td>
                                    <td class="align-right font-black">{{ $fs['synthetique']['vl_a_date_display'] }}</td>
                                    <td class="align-right font-black">{{ $fs['synthetique']['capital_total_investi_display'] }}</td>
                                    <td class="align-right font-black">{{ $fs['synthetique']['nb_parts_cumule_display'] }}</td>
                                    <td class="align-right font-black text-[#009A4D] dark:text-emerald-400 text-sm">
                                        {{ $fs['synthetique']['valorisation_portefeuille_display'] }}
                                    </td>
                                    <td class="align-right font-black {{ $fs['synthetique']['plus_value'] >= 0 ? 'text-[#009A4D] dark:text-emerald-400' : 'text-rose-600' }}">
                                        {{ $fs['synthetique']['plus_value_display'] }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- SECTION II : DÉTAILS DES TRANSACTIONS (PARTS) --}}
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#E5A812]"></span>
                        II. DÉTAILS DES TRANSACTIONS (PARTS)
                    </div>
                    <div class="overflow-x-auto">
                        <table class="kori-statement-table">
                            <thead>
                                <tr>
                                    <th style="width: 18%;">Désignation</th>
                                    <th style="width: 15%;">Nb Parts (Mois précédent)</th>
                                    <th style="width: 15%;">Montant brut investi</th>
                                    <th style="width: 14%;">Frais de souscription</th>
                                    <th style="width: 12%;">Nb Parts (Mois)</th>
                                    <th style="width: 12%;">Nb Parts (Rachetées)</th>
                                    <th style="width: 14%;">Nombre de Parts Actuel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="align-left font-bold">{{ $fs['mouvements']['designation'] }}</td>
                                    <td class="align-right">{{ $fs['mouvements']['nb_parts_precedent_display'] }}</td>
                                    <td class="align-right">{{ $fs['mouvements']['montant_brut_investi_display'] }}</td>
                                    <td class="align-right text-gray-500">{{ $fs['mouvements']['frais_souscription_display'] }}</td>
                                    <td class="align-right font-black text-[#009A4D] dark:text-emerald-400">{{ $fs['mouvements']['nb_parts_mois_display'] }}</td>
                                    <td class="align-right font-black text-rose-600">{{ $fs['mouvements']['nb_parts_rachetees_display'] }}</td>
                                    <td class="align-right font-black text-[#8C4B18] dark:text-amber-400 text-sm">{{ $fs['mouvements']['nb_parts_actuel_display'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Détail des souscriptions individuelles de la période --}}
                    @if(!empty($fs['mouvements']['details_transactions']))
                        <div class="mt-3 p-4 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="text-xs font-extrabold text-gray-800 dark:text-gray-200 mb-2.5 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <x-heroicon-m-queue-list class="w-4 h-4 text-[#E5A812]" />
                                    Souscriptions validées sur la période ({{ count($fs['mouvements']['details_transactions']) }})
                                </span>
                                <span class="text-[11px] text-gray-500">Détail par opération</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead>
                                        <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 pb-1">
                                            <th class="py-1.5 px-2 font-bold">Date Valeur</th>
                                            <th class="py-1.5 px-2 font-bold">Référence</th>
                                            <th class="py-1.5 px-2 text-right font-bold">Montant Brut</th>
                                            <th class="py-1.5 px-2 text-right font-bold">Frais</th>
                                            <th class="py-1.5 px-2 text-right font-bold">Montant Net</th>
                                            <th class="py-1.5 px-2 text-right font-bold">VL Appliquée</th>
                                            <th class="py-1.5 px-2 text-right font-bold">Parts Allouées</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 font-medium">
                                        @foreach($fs['mouvements']['details_transactions'] as $tx)
                                            <tr>
                                                <td class="py-2 px-2">{{ $tx['date'] }}</td>
                                                <td class="py-2 px-2 font-mono text-gray-600 dark:text-gray-400 font-bold">{{ $tx['reference'] }}</td>
                                                <td class="py-2 px-2 text-right font-semibold">{{ number_format($tx['montant_total'], 0, ',', ' ') }} XAF</td>
                                                <td class="py-2 px-2 text-right text-gray-500">{{ number_format($tx['frais'], 0, ',', ' ') }} XAF</td>
                                                <td class="py-2 px-2 text-right font-bold text-gray-900 dark:text-white">{{ number_format($tx['montant_net'], 0, ',', ' ') }} XAF</td>
                                                <td class="py-2 px-2 text-right">{{ number_format($tx['vl'], 2, ',', ' ') }}</td>
                                                <td class="py-2 px-2 text-right font-black text-[#009A4D] dark:text-emerald-400">+{{ number_format($tx['nb_parts'], 2, ',', ' ') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- SECTION III : REGULARISATION --}}
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-gray-600 dark:text-gray-300 mb-1 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#E5A812]"></span>
                        III. REGULARISATION
                    </div>
                    <div class="overflow-x-auto">
                        <table class="kori-statement-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Total encaissé</th>
                                    <th style="width: 25%;">Total des frais de souscription</th>
                                    <th style="width: 25%;">Montant total des souscriptions</th>
                                    <th style="width: 25%;">Montant regularisé</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="align-right font-black">{{ $fs['regularisation']['total_encaisse_display'] }}</td>
                                    <td class="align-right font-semibold text-gray-500">{{ $fs['regularisation']['total_frais_display'] }}</td>
                                    <td class="align-right font-black text-[#009A4D] dark:text-emerald-400">{{ $fs['regularisation']['montant_total_souscriptions_display'] }}</td>
                                    <td class="align-right font-semibold">{{ $fs['regularisation']['montant_regularise_display'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Encadré Valorisation Totale (Bas de tableau - Charte KORI officielle) --}}
                <div class="kori-valorisation-box">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#8C4B18]/10 text-[#8C4B18] dark:text-amber-300 flex items-center justify-center font-black">
                            <x-heroicon-m-shield-check class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wider font-extrabold text-[#8C4B18] dark:text-amber-400">
                                Valorisation Officielle
                            </div>
                            <div class="text-sm sm:text-base font-black uppercase text-gray-900 dark:text-white">
                                AU {{ $fs['valorisation_finale']['date_label'] }}
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-2xl sm:text-3xl font-black text-[#009A4D] dark:text-emerald-400 tracking-tight">
                            {{ $fs['valorisation_finale']['montant_display'] }} <span class="text-sm font-extrabold text-[#8C4B18] dark:text-amber-300">XAF</span>
                        </div>
                        <div class="text-[11px] font-bold text-gray-500 dark:text-gray-400">
                            Actif global net du fonds
                        </div>
                    </div>
                </div>

            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-500/10 text-[#C67C00] flex items-center justify-center mb-3">
                    <x-heroicon-o-document-magnifying-glass class="w-8 h-8" />
                </div>
                <h4 class="text-lg font-black text-gray-900 dark:text-white">Aucun mouvement enregistré sur cette période</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mt-1">
                    Ce client ne possède pas encore de souscriptions validées sur la plage sélectionnée. Essayez d'élargir la période en cliquant sur <button type="button" wire:click="setPeriodAllHistory" class="text-[#009A4D] font-bold underline">« Tout l'historique »</button>.
                </p>
            </div>
        @endforelse

    </div>
</x-filament-panels::page>
