<div 
    class="flex items-center gap-3 py-2 px-1 transition-all duration-300"
    :class="{ 'justify-center': ! $store.sidebar.isOpen }"
>
    <a href="{{ url('/admin') }}" class="flex items-center gap-3">
        <img 
            src="/logo.png" 
            alt="PEK Logo" 
            class="h-10 w-10 object-contain rounded-lg transition-all duration-300 shadow-sm"
            onerror="if (this.src.indexOf('logo-kori.png') === -1) this.src='/logo-kori.png';"
        >
        <span 
            x-show="$store.sidebar.isOpen" 
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-x-2"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 -translate-x-2"
            class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 whitespace-nowrap overflow-hidden select-none"
        >
            PEK Admin
        </span>
    </a>
</div>
