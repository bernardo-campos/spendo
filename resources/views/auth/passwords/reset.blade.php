@php
	$token = request()->route()->parameters()['token'];
	$email = request()->input('email');
@endphp
@extends('adminlte::auth.passwords.reset')