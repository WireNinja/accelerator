<div
    x-data="acceleratorBusinessExceptionModal($el)"
    x-init="init()"
    x-on:{{ $businessExceptionEventName }}.window="show($event.detail)"
    data-default-title="{{ $businessExceptionDefaultTitle }}"
    data-event-name="{{ $businessExceptionEventName }}"
    data-modal-id="{{ $businessExceptionModalId }}"
    data-session-body="{{ $businessExceptionSessionBody }}"
    data-session-title="{{ $businessExceptionSessionTitle }}"
>
    <x-filament::modal
        id="{{ $businessExceptionModalId }}"
        alignment="start"
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
        sticky-header
        width="xl"
        :close-by-clicking-away="false"
    >
        <x-slot name="heading">
            <span x-text="title"></span>
        </x-slot>

        <x-slot name="description">
            <span x-show="body" x-text="body"></span>
        </x-slot>

        <div class="space-y-3">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                Sistem menghentikan aksi ini untuk menjaga konsistensi data. Periksa kondisi bisnis yang disebutkan, lalu ulangi setelah data sudah sesuai.
            </p>
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="close()">
                Tutup
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>

<script>
    (() => {
        if (! window.acceleratorBusinessExceptionModal) {
            window.acceleratorBusinessExceptionModal = (element) => ({
                body: null,
                init() {
                    const sessionTitle = element.dataset.sessionTitle || null
                    const sessionBody = element.dataset.sessionBody || null

                    if (! sessionTitle && ! sessionBody) {
                        return
                    }

                    this.show({
                        title: sessionTitle,
                        body: sessionBody,
                    })
                },
                close() {
                    document.dispatchEvent(new CustomEvent('close-modal', {
                        bubbles: true,
                        composed: true,
                        detail: {
                            id: element.dataset.modalId,
                        },
                    }))
                },
                open() {
                    document.dispatchEvent(new CustomEvent('open-modal', {
                        bubbles: true,
                        composed: true,
                        detail: {
                            id: element.dataset.modalId,
                        },
                    }))
                },
                show(detail = {}) {
                    this.title = detail.title || element.dataset.defaultTitle || 'Aksi tidak dapat diproses'
                    this.body = detail.body || null
                    this.open()
                },
                title: element.dataset.defaultTitle || 'Aksi tidak dapat diproses',
            })
        }

        const bootBusinessExceptionHandler = () => {
            if (window.__filamentBusinessExceptionHandlerBooted) {
                return
            }

            if (! window.Livewire) {
                return
            }

            window.__filamentBusinessExceptionHandlerBooted = true

            const decodeHeader = (value) => value ? decodeURIComponent(value) : null

            const dispatchBusinessException = (detail) => {
                document.dispatchEvent(new CustomEvent(element.dataset.eventName || @js($businessExceptionEventName), {
                    bubbles: true,
                    composed: true,
                    detail,
                }))
            }

            Livewire.interceptRequest(({ onError }) => {
                onError(({ response, preventDefault }) => {
                    if (response?.headers?.get(@js($businessExceptionHeaderName)) !== '1') {
                        return
                    }

                    preventDefault()

                    dispatchBusinessException({
                        title: decodeHeader(response.headers.get(@js($businessExceptionTitleHeaderName))) || @js($businessExceptionDefaultTitle),
                        body: decodeHeader(response.headers.get(@js($businessExceptionBodyHeaderName))),
                    })
                })
            })
        }

        const element = document.currentScript?.previousElementSibling

        if (! element) {
            return
        }

        if (window.Livewire) {
            bootBusinessExceptionHandler()
        }

        document.addEventListener('livewire:init', bootBusinessExceptionHandler, { once: true })
    })()
</script>