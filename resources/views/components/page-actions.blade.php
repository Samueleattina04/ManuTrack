{{-- Pagine autonome (etichette, rapporto): azioni senza gestori inline, che la CSP blocca --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    document.addEventListener('change', function (e) { if (e.target.matches('[data-submit-on-change]')) e.target.form.submit(); });
    document.addEventListener('click', function (e) { if (e.target.closest('[data-print]')) window.print(); });
</script>
