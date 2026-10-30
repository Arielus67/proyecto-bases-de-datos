<script src="{{ asset('theme/libs/jquery/jquery-3.6.4.min.js') }}"></script>
<script src="{{ asset('theme/libs/jquery/jquery-ui.min.js') }}"></script>
<script src="{{ asset('theme/libs/bootstrap-5.0.2-dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('theme/libs/DataTables/datatables.min.js') }}"></script>

<script>
    $(document).ready(function() {
        $('.datatable').DataTable()
    });
</script>

<script>

    document.addEventListener('submit', function (e) {
        var mensaje = e.target.getAttribute('data-confirm');
        if (mensaje && !confirm(mensaje)) {
            e.preventDefault();
        }
    });
</script>