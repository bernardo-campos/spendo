@extends('adminlte::page')

@section('plugins.Datatables', true)

@php($title = 'Usuarios')

@section('title', $title)

@section('content_header')
    <div class="d-flex">
        <div>
            <h1 class="m-0 text-dark">{{ $title }}</h1>
            <small></small>
        </div>
    </div>
@endsection

@section('content')

<div class="card">
    <div class="card-body">

    <table id="users" class="table table-sm responsive nowrap">
        <thead>
            <tr>
                <th>Id</th>
                <th>Nombre</th>
                <th>E-mail</th>
                <th>Verificado</th>
                <th>Creado</th>
                <th>Actualizado</th>
                <th></th>
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

var dt = $('#users').DataTable({
    language: {
        url: "https://cdn.datatables.net/plug-ins/2.2.1/i18n/es-ES.json"
    },
    stateSave: true,
    processing: true,
    serverSide: true,
    ajax: '{{ route('users.index') }}',
    search: {
        return: true,
    },
    columns: [
        { data: 'id',               name: 'users.id' },
        { data: 'name',             name: 'users.name' },
        { data: 'email',            name: 'users.email' },
        { data: 'email_verified_at',name: 'users.email_verified_at', render: DataTable.render.toLocaleTimeString() },
        { data: 'created_at',       name: 'users.created_at', render: DataTable.render.toLocaleTimeString() },
        { data: 'updated_at',       name: 'users.updated_at', render: DataTable.render.toLocaleTimeString() },
        {
            data: null,
            defaultContent: '',
            orderable: false,
            render: function ( data, type, row, meta ) {
                if (type === 'display') {

                    let editButton = row.urls.edit
                        ? `<a title="Editar" href="${row.urls.edit}" class="btn btn-sm btn-outline-primary py-0 px-1 ml-1">Editar</a>`
                        : '';

                    return `<div class="d-flex">${editButton}</div>`;
                }
            }
        },
    ]
}); // datatable

}); // document.ready
</script>
@endpush