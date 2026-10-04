<x-filament-widgets::widget>
    <div class="space-y-6" wire:poll.60s>
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Vue d’ensemble de l’activité</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Mise à jour le {{ $updatedAt }} · {{ $timezone }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        href="{{ \App\Filament\Pages\KpiAnalytics::getUrl() }}"
                        tag="a"
                        icon="heroicon-o-chart-bar-square"
                        color="primary"
                    >
                        Voir les Graphiques des KPI
                    </x-filament::button>
                    <x-filament::button wire:click="$refresh" wire:loading.attr="disabled" icon="heroicon-o-arrow-path" color="gray">
                        Actualiser
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @foreach ($groups as $heading => $cards)
            <section class="space-y-4" aria-label="{{ $heading }}" wire:key="group-{{ $loop->index }}">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $heading }}</h2>
                <div class="grid gap-6 md:grid-cols-2 {{ $loop->first ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
                    @foreach ($cards as $card)
                        {{ \Filament\Widgets\StatsOverviewWidget\Stat::make($card['label'], $card['formatted'])
                            ->description($card['description'])
                            ->descriptionIcon($card['url'] ? 'heroicon-m-arrow-top-right-on-square' : 'heroicon-m-information-circle')
                            ->icon($card['icon'])
                            ->color($loop->parent->first ? 'primary' : 'warning')
                            ->url($card['url']) }}
                    @endforeach
                </div>
            </section>
        @endforeach

        <x-filament::section heading="Comprendre les indicateurs" collapsible collapsed>
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <p>Les quatre indicateurs communs couvrent toute l’activité. Les blocs métiers sont affichés selon vos permissions actuelles. Une carte ouvre la liste filtrée uniquement si vous avez accès aux dossiers correspondants.</p>
                <p>Le jour va de 00 h 00 à minuit dans le fuseau {{ $timezone }}. Les souscriptions du jour sont les demandes créées aujourd’hui, quel que soit leur statut. Les inscriptions concernent uniquement les comptes clients.</p>
                <p>Les montants en FCFA correspondent au montant brut des souscriptions au statut « Succès », frais inclus. Ils ne représentent pas la valeur actuelle des portefeuilles. Les fonds reçus mais non encore valorisés figurent dans les indicateurs métiers.</p>
                <p>Le montant confirmé du jour suit la date de valeur ; à défaut, la date de réception des fonds, puis de confirmation du paiement. Pour les anciens dossiers sans ces dates, la date de création est utilisée. Les cumuls couvrent tout l’historique.</p>
                <p>Les opérations portant un marqueur de simulation ou de staging sont exclues. « VL du jour non renseignées » indique une absence de publication aujourd’hui, sans présumer d’un retard réglementaire. Actualisation automatique toutes les 60 secondes lorsque la page est visible.</p>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
