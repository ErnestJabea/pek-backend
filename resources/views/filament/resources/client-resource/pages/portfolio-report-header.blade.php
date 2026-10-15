<div class="fi-header flex flex-col gap-3.5">
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

    {{-- Boutons d'action présentés en vertical sur 2 lignes (Retour aux clients en premier) --}}
    <div class="flex flex-col gap-2.5 pt-1">
        {{-- Ligne 1 : Retour aux clients en premier --}}
        <div>
            <a href="{{ \App\Filament\Resources\ClientResource::getUrl('index') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm transition">
                <x-heroicon-m-arrow-left class="w-4 h-4 text-gray-500" />
                Retour aux clients
            </a>
        </div>

        {{-- Ligne 2 : Télécharger le Relevé PDF & Aperçu / Imprimer --}}
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('client.statement.pdf', ['client' => $record->id, 'start_date' => $startDate, 'end_date' => $endDate, 'product_id' => $productId]) }}"
               target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-[#E5A812] hover:bg-[#D4950A] text-black shadow-md shadow-amber-500/20 transition">
                <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                Télécharger le Relevé PDF
            </a>

            <a href="{{ route('client.statement.preview', ['client' => $record->id, 'start_date' => $startDate, 'end_date' => $endDate, 'product_id' => $productId]) }}"
               target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-[#009A4D] hover:bg-[#007A3D] text-white shadow-md shadow-emerald-500/20 transition">
                <x-heroicon-m-printer class="w-4 h-4" />
                Aperçu / Imprimer
            </a>
        </div>
    </div>
</div>
