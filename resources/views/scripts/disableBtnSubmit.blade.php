<script type="text/javascript">
   $('form.with-confirmation').on('submit', function (event) {
      var confirmationMessage = $(this).data('confirmation');
      if (!confirmationMessage) {
         confirmationMessage = 'Se enviará el formulario, ¿Continuar?';
      }

      if (!confirm(confirmationMessage)) {
         event.preventDefault();
         return;
      }

      $(this).find('button[type="submit"], input[type="submit"]')
         .prop('disabled', true)
         .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aguarde un momento...');
   });

   $('form.without-confirmation').on('submit', function (event) {
      $(this).find('button[type="submit"], input[type="submit"]')
         .prop('disabled', true)
         .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aguarde un momento...');
   });
</script>