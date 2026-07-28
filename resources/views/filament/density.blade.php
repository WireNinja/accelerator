@if (config('accelerator.ui.density') === 'compact')
    <script data-navigate-once>
        window.applyAcceleratorDensity = () => {
            document.documentElement.classList.add('accelerator-density-compact')
        }

        window.applyAcceleratorDensity()
        document.addEventListener('livewire:navigated', window.applyAcceleratorDensity)
    </script>
@endif
