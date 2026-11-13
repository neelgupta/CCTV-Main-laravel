@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('reportChart', (config) => ({
                chart: null,
                loading: true,
                empty: Boolean(config?.empty),
                init() {
                    this.$nextTick(() => {
                        if (this.empty) {
                            this.loading = false;
                            return;
                        }

                        const canvas = this.$refs.canvas;
                        if (!canvas || typeof Chart === 'undefined') {
                            this.loading = false;
                            this.empty = true;
                            return;
                        }

                        const context = canvas.getContext('2d');
                        if (!context) {
                            this.loading = false;
                            this.empty = true;
                            return;
                        }

                        this.chart = new Chart(context, {
                            type: config.type,
                            data: config.data,
                            options: config.options ?? {},
                        });

                        this.loading = false;
                    });
                },
                destroy() {
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                },
            }));
        });
    </script>
@endpush
