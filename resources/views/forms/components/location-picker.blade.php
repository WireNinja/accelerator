@php
    $latitudePath = $getStatePath();
    $pathSegments = explode('.', $latitudePath);
    array_pop($pathSegments);
    $longitudePath = implode('.', $pathSegments) . '.' . $field->getLongitudeField();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            latitude: $wire.{{ $applyStateBindingModifiers("\$entangle('{$latitudePath}')") }},
            longitude: $wire.{{ $applyStateBindingModifiers("\$entangle('{$longitudePath}')") }},
            map: null,
            marker: null,
            resizeObserver: null,
            destroyed: false,
            init() {
                const initialize = () => {
                    if (this.destroyed) return
                    if (typeof window.L === 'undefined') return requestAnimationFrame(initialize)

                    this.$nextTick(() => {
                        if (this.destroyed || this.map) return

                        const element = this.$refs.map
                        const latitude = parseFloat(this.latitude) || -7.2575
                        const longitude = parseFloat(this.longitude) || 112.7521

                        element.__acceleratorLocationPicker?.remove()
                        this.map = window.L.map(element).setView([latitude, longitude], 13)
                        element.__acceleratorLocationPicker = this.map

                        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>',
                            maxZoom: 19,
                        }).addTo(this.map)

                        if (this.latitude && this.longitude) {
                            this.marker = window.L.marker([latitude, longitude], { draggable: true }).addTo(this.map)
                            this.bindMarker()
                        }

                        this.map.on('click', (event) => this.placeMarker(event.latlng))
                        this.resizeObserver = new ResizeObserver(() => this.map?.invalidateSize())
                        this.resizeObserver.observe(element)
                        setTimeout(() => this.map?.invalidateSize(), 0)
                    })
                }

                initialize()
            },
            placeMarker(position) {
                if (! this.map) return

                this.latitude = position.lat
                this.longitude = position.lng

                if (this.marker) {
                    this.marker.setLatLng(position)
                } else {
                    this.marker = window.L.marker(position, { draggable: true }).addTo(this.map)
                    this.bindMarker()
                }
            },
            bindMarker() {
                this.marker?.on('dragend', (event) => {
                    const position = event.target.getLatLng()
                    this.latitude = position.lat
                    this.longitude = position.lng
                })
            },
            destroy() {
                this.destroyed = true
                this.resizeObserver?.disconnect()
                this.map?.remove()
                this.map = null
            },
        }"
        x-load-js="[@js(\Filament\Support\Facades\FilamentAsset::getScriptSrc('leaflet', 'wireninja/accelerator'))]"
        x-load-css="[@js(\Filament\Support\Facades\FilamentAsset::getStyleHref('leaflet', 'wireninja/accelerator'))]"
    >
        <div x-ref="map" wire:ignore class="h-96 w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"></div>

        <div class="mt-1 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span>Lat: <span x-text="latitude ? Number(latitude).toFixed(6) : '—'"></span></span>
            <span>Lng: <span x-text="longitude ? Number(longitude).toFixed(6) : '—'"></span></span>
            <span class="ml-auto italic">Klik peta atau geser penanda untuk memilih koordinat</span>
        </div>
    </div>
</x-dynamic-component>
