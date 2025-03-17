@extends('adminlte::page')

@section('plugins.Datatables', true)
@section('plugins.iCheck', true)

@php($title = 'Nueva Categoría')

@section('title', $title)

@section('content_header')
    <div class="d-flex">
        <div>
            <h1 class="m-0 text-dark">{{ $title }}</h1>
            <small></small>
        </div>
        <a href="{{ route('categories.index') }}" class="ml-auto">
            <x-adminlte-button label="Volver" theme="light" icon="fas fa-chevron-left mr-2"/>
        </a>
    </div>
@endsection

@section('content')
<form class="form-horizontal" action="{{ route('categories.store') }}" method="POST">
    @csrf
    <div class="card">
        <div class="card-body">

            <div class="row col-lg-10 col-xl-8 offset-lg-1 offset-xl-2">

                <x-adminlte-input
                    value="{{ old('name') }}"
                    id="name"
                    name="name"
                    type="text"
                    label="Nombre de la categoría"
                    fgroup-class="col-sm-4"
                />

                <div class="col-4">
                    <label>Tipo</label>
                    <div class="mb-2 d-flex">
                        <div class="icheck-primary">
                            <input
                                class="form-check-input"
                                type="radio"
                                name="type"
                                value="expense"
                                id="expense"
                                @checked((old('type') == 'expense'))
                                >
                            <label class="form-check-label" for="expense">{{ __('expense') }}</label>
                        </div>
                        <div class="icheck-primary ml-3">
                            <input
                                class="form-check-input"
                                type="radio"
                                name="type"
                                value="income"
                                id="income"
                                @checked((old('type') == 'income'))
                                >
                            <label class="form-check-label" for="income">{{ __('income') }}</label>
                        </div>
                    </div>
                    @error('type')
                        <span class="invalid-feedback d-block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

            </div>
        </div> <!-- .card-body -->

        <div class="card-footer d-flex flex-row-reverse">
            <x-adminlte-button class="ml-2"
                label="Guardar"
                theme="primary"
                icon="fas fa-save"
                type="submit"
            />
            <a href="{{ route('categories.index') }}" class="ml-auto">
                <x-adminlte-button label="Cancelar" theme="light" icon="fas fa-chevron-left mr-2"/>
            </a>
        </div>
    </div>
</form>
@endsection
