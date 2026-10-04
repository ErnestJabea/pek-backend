<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Observatoire Visuel des Performances</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Analyse approfondie et courbes d'évolution des flux financiers, de la conformité KYC et de la croissance des clients.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        href="{{ \App\Filament\Pages\Dashboard::getUrl() }}"
                        tag="a"
                        icon="heroicon-o-arrow-left"
                        color="gray"
                    >
                        Retour au Tableau de bord
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @livewire(\Filament\Widgets\WidgetList::class, ['widgets' => $this->getWidgets()])
    </div>
</x-filament-panels::page>
