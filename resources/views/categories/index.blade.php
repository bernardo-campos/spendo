@extends('adminlte::page')

@section('plugins.Datatables', true)

@php($title = 'Categorías')

@section('title', $title)

@section('content_header')
    <div class="d-flex">
        <div>
            <h1 class="m-0 text-dark">{{ $title }}</h1>
            <small></small>
        </div>
        <a href="{{ route('categories.create') }}" class="ml-auto">
            <x-adminlte-button label="Nueva categoría" theme="outline-primary"/>
        </a>
    </div>
@endsection

@section('content')

<div class="card">
    <div class="card-body">

        <table id="categories" class="table table-sm responsive nowrap">
        <thead>
            <tr>
                <th>Categoría</th>
                <th style="display: none;">Tipo</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>

    </div>
</div>

@endsection

@push('js')
<script>
$(document).ready(function () {

var dt = $('#categories').DataTable({
    language: {
        url: "https://cdn.datatables.net/plug-ins/2.2.1/i18n/es-ES.json"
    },
    stateSave: true,
    processing: true,
    serverSide: false,
    paging: false,
    ajax: '{{ route('categories.index') }}',
    search: {
        return: true,
    },
    layout: {
        bottomStart: null,
        topStart: function () {
            return `
            <div class="form-group d-flex">
                <label class="m-0">Tipo:</label>
                <div class="form-check d-flex align-items-center ml-2">
                    <input class="form-check-input" type="radio" name="type" id="expenses" value="expense">
                    <label class="form-check-label" for="expenses">Gastos</label>
                </div>
                <div class="form-check d-flex align-items-center ml-2">
                    <input class="form-check-input" type="radio" name="type" id="incomes" value="income">
                    <label class="form-check-label" for="incomes">Ingresos</label>
                </div>
            </div>`;
        }
    },
    initComplete: function () {
        $("#expenses").prop("checked", true).trigger('change');
    },
    columns: [
        { data: 'name', name: 'name' },
        { data: 'type', name: 'type', visible: false},
    ]
}); // datatable

$(document).on('change', 'input[name="type"]', function () {
    var selectedType = $('input[name="type"]:checked').val();
    dt.column(1)
        .data()
        .search(function (value, row) {
            return row.type == selectedType ? true : false;
        })
        .draw()
});

}); // document.ready
</script>
@endpush