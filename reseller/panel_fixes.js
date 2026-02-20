
// --- MISSING FUNCTIONS FIX ---

function openPaymentModal() {
    if (document.getElementById('modal-report-payment')) {
        openModal('modal-report-payment');
    } else {
        const m = document.getElementById('modal-report-payment');
        if (m) m.style.display = 'flex';
    }
}

function getResellerCode(id) {
    Swal.fire({
        title: 'Obteniendo Codigo...',
        didOpen: () => Swal.showLoading(),
        background: '#111', color: '#fff'
    });

    fetch(`api.php?action=get_reseller_code&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.code) {
                Swal.fire({
                    title: 'Código de Acceso',
                    html: `<div style="font-size:2rem; font-weight:bold; color:#ffbb00; letter-spacing:2px; margin:10px 0;">${res.code}</div>
                       <p style="color:#aaa">Este código expira en breve.</p>`,
                    background: '#111', color: '#fff',
                    confirmButtonText: 'Copiar',
                    showCancelButton: true,
                    cancelButtonText: 'Cerrar'
                }).then(r => {
                    if (r.isConfirmed) {
                        navigator.clipboard.writeText(res.code);
                        SwalSuccess('Copiado');
                    }
                });
            } else {
                SwalError(res.message || 'No disponible');
            }
        })
        .catch(() => SwalError('Error de conexión'));
}
