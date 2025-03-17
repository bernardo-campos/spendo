<script>
	@if (session()->has('success'))
		Swal.fire({
			icon: 'success',
			title: 'Éxito',
			html: '{{ session('success') }}',
		});
	@endif

	@if (session()->has('error'))
		Swal.fire({
			icon: 'error',
			title: 'Error',
			html: '{{ session('error') }}',
		});
	@endif
</script>