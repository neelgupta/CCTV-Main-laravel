@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('leadsReportExport', (exportUrl) => ({
                exporting: false,
                async exportReport() {
                    if (this.exporting) {
                        return;
                    }

                    this.exporting = true;

                    if (window.Swal) {
                        Swal.fire({
                            title: 'Exporting...',
                            text: 'Preparing your leads report Excel file.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => Swal.showLoading(),
                        });
                    }

                    try {
                        const response = await fetch(exportUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Export failed');
                        }

                        const blob = await response.blob();
                        const disposition = response.headers.get('Content-Disposition') || '';
                        const match = disposition.match(/filename="?([^";]+)"?/i);
                        const fileName = match?.[1] || `leads-report-${new Date().toISOString().slice(0, 10)}.xlsx`;
                        const objectUrl = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = objectUrl;
                        link.download = fileName;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        window.URL.revokeObjectURL(objectUrl);

                        if (window.Swal) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Export complete',
                                text: 'Your leads report has been downloaded.',
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false,
                            });
                        }
                    } catch (error) {
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Export failed',
                                text: 'Unable to export the leads report. Please try again.',
                                confirmButtonColor: '#0f172a',
                            });
                        } else if (window.appAlert) {
                            window.appAlert('Unable to export the leads report. Please try again.', 'Export failed', 'error');
                        }
                    } finally {
                        this.exporting = false;
                    }
                },
            }));
        });
    </script>
@endpush
