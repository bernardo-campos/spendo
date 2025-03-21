@extends('adminlte::components.form.input-group-component')

{{-- Set errors bag internallly --}}

@php($setErrorsBag($errors ?? null))

{{-- Set input group item section --}}

@section('input_group_item')

    {{-- Select --}}
    <select id="{{ $id }}" name="{{ $name }}"
        {{ $attributes->merge(['class' => $makeItemClass()]) }}>
        {{ $slot }}
    </select>

@overwrite

{{-- Add plugin initialization and configuration code --}}

@push('js')
<script>

    $(() => {
        $('#{{ $id }}').select2( {
            ...@json($config),
            ...{
                language: {
                  noResults: () => 'No se encontraron resultados',
                }
            }
        } ).val(null).trigger( 'change');

        // Add support to auto select old submitted values in case of
        // validation errors.

        @if($errors->any() && $enableOldSupport)

            let oldOptions = @json(collect($getOldValue($errorKey)));

            $('#{{ $id }} option').each(function()
            {
                let value = $(this).val() || $(this).text();
                $(this).prop('selected', oldOptions.includes(value));
            });

            $('#{{ $id }}').trigger('change');

        @endif
    })

</script>
@once
<script type="text/javascript">
    {{-- https://forums.select2.org/t/search-being-unfocused/1203/10 --}}
    // hack to fix jquery 3.6 focus security patch that bugs auto search in select-2
    $(document).on('select2:open', () => {
        document.querySelector('.select2-search__field').focus();
    });

    $(document).on('focus', '.select2-selection.select2-selection--single', function (e) {
        $(this).closest(".select2-container").siblings('select:enabled').select2('open');
    });

    $('select.select2').on('select2:closing', function (e) {
    $(e.target).data("select2").$selection.one('focus focusin', function (e) {
        e.stopPropagation();
    });
    });
</script>
@endonce
@endpush

{{-- CSS workarounds for the Select2 plugin --}}
{{-- NOTE: this may change with newer plugin versions --}}

@once
@push('css')
<style type="text/css">

    {{-- SM size setup --}}
    .input-group-sm .select2-selection--single {
        height: calc(1.8125rem + 2px) !important
    }
    .input-group-sm .select2-selection--single .select2-selection__rendered,
    .input-group-sm .select2-selection--single .select2-selection__placeholder {
        font-size: .875rem !important;
        line-height: 2.125;
    }
    .input-group-sm .select2-selection--multiple {
        min-height: calc(1.8125rem + 2px) !important
    }
    .input-group-sm .select2-selection--multiple .select2-selection__rendered {
        font-size: .875rem !important;
        line-height: normal;
    }

    {{-- LG size setup --}}
    .input-group-lg .select2-selection--single {
        height: calc(2.875rem + 2px) !important;
    }
    .input-group-lg .select2-selection--single .select2-selection__rendered,
    .input-group-lg .select2-selection--single .select2-selection__placeholder {
        font-size: 1.25rem !important;
        line-height: 2.25;
    }
    .input-group-lg .select2-selection--multiple {
        min-height: calc(2.875rem + 2px) !important
    }
    .input-group-lg .select2-selection--multiple .select2-selection__rendered {
        font-size: 1.25rem !important;
        line-height: 1.7;
    }

    {{-- Enhance the plugin to support readonly attribute --}}
    select[readonly].select2-hidden-accessible + .select2-container {
        pointer-events: none;
        touch-action: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
        background: #e9ecef;
        box-shadow: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-search__field {
        display: none;
    }

</style>
@endpush
@endonce
