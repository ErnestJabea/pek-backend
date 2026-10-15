<div class="fi-header flex flex-col gap-3.5">
    <style>
        .kori-header-btn-back {
            background-color: #FFFFFF !important;
            color: #1F2937 !important;
            border: 1px solid #D1D5DB !important;
            font-weight: 700 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
        }
        .kori-header-btn-back:hover {
            background-color: #F3F4F6 !important;
            color: #111827 !important;
        }
        .dark .kori-header-btn-back {
            background-color: #1E293B !important;
            color: #F1F5F9 !important;
            border-color: #475569 !important;
        }
        .dark .kori-header-btn-back:hover {
            background-color: #334155 !important;
        }
        .kori-header-btn-pdf {
            background-color: #E5A812 !important;
            color: #000000 !important;
            border: 1px solid #C67C00 !important;
            font-weight: 800 !important;
            box-shadow: 0 2px 8px rgba(229, 168, 18, 0.35) !important;
        }
        .kori-header-btn-pdf:hover {
            background-color: #D4950A !important;
            color: #000000 !important;
        }
        .kori-header-btn-print {
            background-color: #009A4D !important;
            color: #FFFFFF !important;
            border: 1px solid #007A3D !important;
            font-weight: 800 !important;
            box-shadow: 0 2px 8px rgba(0, 154, 77, 0.35) !important;
        }
        .kori-header-btn-print:hover {
            background-color: #007A3D !important;
            color: #FFFFFF !important;
        }
    </style>

    {{-- Fil d'Ariane --}}
    @if (!empty($breadcrumbs))
        <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" class="mb-0.5" />
    @endif

    {{-- Titre et sous-titre pleine largeur (aucun écrasement de texte) --}}
    <div>
        <h1 class="fi-header-heading text-2xl sm:text-3xl font-black tracking-tight text-gray-950 dark:text-white">
            {{ $heading }}
        </h1>
        @if (!empty($subheading))
            <p class="fi-header-subheading mt-1.5 text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                {{ $subheading }}
            </p>
        @endif
    </div>

    {{-- Boutons d'action sur UNE SEULE LIGNE (Retour aux clients en premier) --}}
    <div class="flex flex-wrap items-center gap-3 pt-2">
        {{-- 1. Retour aux clients en premier --}}
        <a href="{{ \App\Filament\Resources\ClientResource::getUrl('index') }}"
           class="kori-header-btn-back inline-flex items-center gap-2 px-4 py-2.5 text-xs rounded-xl transition"
           style="background-color: #FFFFFF !important; color: #1F2937 !important; border: 1px solid #D1D5DB !important; font-weight: 700 !important;">
            <x-heroicon-m-arrow-left class="w-4 h-4 text-gray-500" />
            Retour aux clients
        </a>

        {{-- 2. Télécharger le Relevé PDF --}}
        <a href="{{ route('client.statement.pdf', ['client' => $record->id, 'start_date' => $startDate, 'end_date' => $endDate, 'product_id' => $productId]) }}"
           target="_blank"
           class="kori-header-btn-pdf inline-flex items-center gap-2 px-4 py-2.5 text-xs rounded-xl transition"
           style="background-color: #E5A812 !important; color: #000000 !important; border: 1px solid #C67C00 !important; font-weight: 800 !important;">
            <x-heroicon-m-arrow-down-tray class="w-4 h-4 text-black" />
            Télécharger le Relevé PDF
        </a>

        {{-- 3. Aperçu / Imprimer --}}
        <a href="{{ route('client.statement.preview', ['client' => $record->id, 'start_date' => $startDate, 'end_date' => $endDate, 'product_id' => $productId]) }}"
           target="_blank"
           class="kori-header-btn-print inline-flex items-center gap-2 px-4 py-2.5 text-xs rounded-xl transition"
           style="background-color: #009A4D !important; color: #FFFFFF !important; border: 1px solid #007A3D !important; font-weight: 800 !important;">
            <x-heroicon-m-printer class="w-4 h-4 text-white" />
            Aperçu / Imprimer
        </a>
    </div>
</div>
