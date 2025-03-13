@extends('adminlte::page')

@section('plugins.iCheck', true)

@php($title = 'Editar usuario')

@section('title', $title)

@section('content_header')
    <div class="d-flex">
        <div>
            <h1 class="m-0 text-dark">{{ $title }}</h1>
            <small></small>
        </div>
        <a href="{{ route('users.index') }}" class="ml-auto">
            <x-adminlte-button label="Volver al listado" theme="light" icon="fas fa-chevron-left mr-2"/>
        </a>
    </div>
@endsection

@section('content')

<form class="form-horizontal" action="{{ route('users.update', $user) }}" method="POST">
    @csrf @method('PUT')
    <div class="card">
        <div class="card-body">

            <div class="row">
                <x-adminlte-input
                    value="{{ old('name', $user->name) }}"
                    id="name"
                    name="name"
                    type="text"
                    label="Nombre"
                    fgroup-class="col-12"
                />
                <x-adminlte-input
                    value="{{ old('email', $user->email) }}"
                    id="email"
                    name="email"
                    type="email"
                    label="Email"
                    fgroup-class="col-12"
                />
                <x-adminlte-input
                    id="password"
                    name="password"
                    type="text"
                    label="Contraseña"
                    placeholder="Dejar en blanco para mantener la contraseña"
                    fgroup-class="col-12"
                />

            </div>

            <div class="row">
                <div class="col-4">
                    <label>Roles</label>
                    @foreach ($roles as $role)
                        <div class="mb-2">
                            <div class="icheck-primary">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="role_ids[]"
                                    value="{{ $role->id }}"
                                    id="rol_{{ $role->id }}"
                                    @checked(collect(old('role_ids') ?? $user->roles->pluck('id')->toArray())->contains($role->id))
                                    >
                                <label class="form-check-label" for="rol_{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                            <ul class="list-unstyled ml-4">
                                @foreach ($role->permissions as $permission)
                                    <li class="text-secondary">{{ $permission->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <div class="col-4">
                    <label>Permisos directos</label>
                    @foreach ($permissions as $permission)
                        <div class="mb-2">
                            <div class="icheck-primary">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="permission_ids[]"
                                    value="{{ $permission->id }}"
                                    id="permission_{{ $permission->id }}"
                                    @checked(collect(old('permission_ids') ?? $user->permissions->pluck('id')->toArray())->contains($permission->id))
                                    >
                                <label class="form-check-label" for="permission_{{ $permission->id }}">{{ $permission->name }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <div class="card-footer d-flex flex-row-reverse">
            <x-adminlte-button class="ml-2"
                label="Guardar"
                theme="primary"
                icon="fas fa-save"
                type="submit"
            />
            <a href="{{ route('users.index') }}" class="ml-auto">
                <x-adminlte-button label="Cancelar" theme="light" icon="fas fa-chevron-left mr-2"/>
            </a>
        </div>
    </div>
</form>
@endsection
