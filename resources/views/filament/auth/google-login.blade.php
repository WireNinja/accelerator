<div
    x-data="{}"
    x-load-js="[{{js_iconify()}}]"
    class="flex flex-col items-center justify-center space-y-4 pt-4">
    <div class="relative flex w-full items-center py-2">
        <div class="flex-grow border-t border-gray-200 dark:border-gray-700"></div>
        <span class="mx-4 flex-shrink text-[10px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
            Atau masuk dengan
        </span>
        <div class="flex-grow border-t border-gray-200 dark:border-gray-700"></div>
    </div>

    <a href="{{ route('auth.google.redirect') }}"
        class="flex w-full items-center justify-center space-x-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition duration-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
        <iconify-icon icon="logos:google-icon"></iconify-icon>
        <span>Masuk dengan Google</span>
    </a>
</div>