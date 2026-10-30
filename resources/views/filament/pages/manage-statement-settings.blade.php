<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <x-filament::button type="submit" size="lg">
                Enregistrer les paramètres
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
