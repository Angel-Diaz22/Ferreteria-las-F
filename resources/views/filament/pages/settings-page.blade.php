<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
            <button
                type="submit"
                class="px-6 py-2.5 rounded-xl text-sm font-black text-white transition-all shadow-md flex items-center gap-2 cursor-pointer hover:opacity-90"
                style="background-color: #ea580c;"
            >
                <x-heroicon-o-check class="w-5 h-5 text-white" />
                <span>Guardar Configuración</span>
            </button>
        </div>
    </form>
</x-filament-panels::page>
