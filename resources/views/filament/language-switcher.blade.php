<div class="flex items-center gap-x-4 px-4">
    <a href="{{ route('language.switch', 'fr') }}" class="text-sm font-medium {{ app()->getLocale() === 'fr' ? 'text-primary-600' : 'text-gray-500' }}">
        FR
    </a>
    <span class="text-gray-300">|</span>
    <a href="{{ route('language.switch', 'en') }}" class="text-sm font-medium {{ app()->getLocale() === 'en' ? 'text-primary-600' : 'text-gray-500' }}">
        EN
    </a>
</div>
